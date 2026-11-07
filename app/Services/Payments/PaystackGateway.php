<?php

namespace App\Services\Payments;

use App\Actions\Invoices\MarkInvoicePaid;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaystackGateway implements PaymentGateway
{
    public const BASE_URL = 'https://api.paystack.co';

    public function __construct(protected MarkInvoicePaid $markPaid) {}

    public function verifyCredentials(array $credentials): string
    {
        $response = $this->send(fn () => $this->client($credentials['secret_key'] ?? '')->get('/balance'));

        if ($response->status() === 401) {
            throw new PaymentException(__('Paystack rejected this secret key.'));
        }

        if ($response->failed()) {
            throw new PaymentException(__('Paystack could not be reached. Try again in a moment.'));
        }

        return 'Paystack account';
    }

    public function startPayment(Invoice $invoice, PaymentAccount $account, string $returnUrl, string $cancelUrl): string
    {
        $reference = 'inv-'.$invoice->id.'-'.Str::lower(Str::random(10));
        $currency = strtoupper($invoice->currency);

        $response = $this->send(fn () => $this->client($account->credential('secret_key'))->post('/transaction/initialize', [
            'email' => $this->customerEmail($invoice),
            'amount' => $invoice->total_cents,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $returnUrl,
            'metadata' => ['invoice_id' => (string) $invoice->id, 'invoice_number' => $invoice->number],
        ]));

        $url = $response->json('data.authorization_url');

        if ($response->failed() || blank($url)) {
            $paystackMessage = trim((string) ($response->json('message') ?? ''));

            Log::warning('Paystack could not initialize a payment.', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'currency' => $currency,
                'message' => $paystackMessage !== '' ? $paystackMessage : null,
                'body' => $response->json(),
            ]);

            throw new PaymentException($this->initializeFailureMessage($paystackMessage, $currency));
        }

        $invoice->forceFill(['pending_reference' => $reference])->save();

        return $url;
    }

    public function completePayment(Invoice $invoice, PaymentAccount $account, Request $request): bool
    {
        $reference = (string) ($request->query('reference') ?: $invoice->pending_reference);

        if ($reference === '' || $reference !== $invoice->pending_reference) {
            return false;
        }

        $response = $this->send(fn () => $this->client($account->credential('secret_key'))->get('/transaction/verify/'.rawurlencode($reference)));

        if ($response->failed()) {
            throw new PaymentException(__('We could not confirm the payment yet. Refresh in a moment.'));
        }

        return $this->settle($invoice, $response->json('data') ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function settle(Invoice $invoice, array $data): bool
    {
        $isFullPayment = strtolower((string) ($data['status'] ?? '')) === 'success'
            && (int) ($data['amount'] ?? -1) === $invoice->total_cents
            && strtolower((string) ($data['currency'] ?? '')) === $invoice->currency
            && (string) ($data['metadata']['invoice_id'] ?? '') === (string) $invoice->id;

        if (! $isFullPayment) {
            return false;
        }

        $this->markPaid->handle($invoice, 'paystack', (string) ($data['reference'] ?? $invoice->pending_reference));

        return true;
    }

    public function refundPayment(Invoice $invoice, PaymentAccount $account): void
    {
        $response = $this->send(fn () => $this->client($account->credential('secret_key'))->post('/refund', [
            'transaction' => $invoice->payment_reference,
        ]));

        if ($response->failed()) {
            throw new PaymentException((string) ($response->json('message') ?? __('Paystack could not refund this payment.')));
        }
    }

    protected function client(?string $secretKey): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken((string) $secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    /**
     * Paystack requires a real email address on every checkout.
     */
    protected function customerEmail(Invoice $invoice): string
    {
        $email = trim((string) ($invoice->lead->email ?? ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'customers.local';

        return 'invoice-'.$invoice->id.'@'.$host;
    }

    protected function initializeFailureMessage(string $paystackMessage, string $currency): string
    {
        $lower = Str::lower($paystackMessage);

        if ($lower !== '' && (str_contains($lower, 'currency') || str_contains($lower, 'not supported'))) {
            return __('Paystack cannot charge this invoice in :currency. Enable :currency on the Paystack account, or pay with another provider.', [
                'currency' => $currency,
            ]);
        }

        if ($lower !== '' && str_contains($lower, 'email')) {
            return __('Paystack needs a valid customer email on the lead before checkout can start.');
        }

        if ($lower !== '' && (str_contains($lower, 'invalid key') || str_contains($lower, 'authorization') || str_contains($lower, 'secret'))) {
            return __('The connected Paystack secret key was rejected. Update the keys on the Payments page.');
        }

        if ($paystackMessage !== '') {
            return __('Paystack could not start this payment: :message', ['message' => $paystackMessage]);
        }

        return __('The payment page could not be opened. Please try again.');
    }

    /**
     * @param  Closure(): Response  $request
     */
    protected function send(Closure $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new PaymentException(__('Paystack could not be reached. Try again in a moment.'));
        }
    }
}

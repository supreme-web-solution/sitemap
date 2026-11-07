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
use Illuminate\Support\Str;

class FlutterwaveGateway implements PaymentGateway
{
    public const BASE_URL = 'https://api.flutterwave.com/v3';

    public function __construct(
        protected MarkInvoicePaid $markPaid,
        protected ExchangeRateConverter $exchangeRates,
    ) {}

    public function verifyCredentials(array $credentials): string
    {
        $response = $this->send(fn () => $this->client($credentials['secret_key'] ?? '')->get('/balances'));

        if ($response->status() === 401) {
            throw new PaymentException(__('Flutterwave rejected this secret key.'));
        }

        if ($response->failed()) {
            throw new PaymentException(__('Flutterwave could not be reached. Try again in a moment.'));
        }

        return 'Flutterwave account';
    }

    public function startPayment(Invoice $invoice, PaymentAccount $account, string $returnUrl, string $cancelUrl): string
    {
        $reference = 'inv-'.$invoice->id.'-'.Str::lower(Str::random(10));
        $charge = $this->exchangeRates->chargeForInvoice(
            $invoice->total_cents,
            $invoice->currency,
            ExchangeRateConverter::LOCAL_CURRENCY,
        );
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'customers.local';
        $email = trim((string) ($invoice->lead->email ?? ''));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'invoice-'.$invoice->id.'@'.$host;

        $response = $this->send(fn () => $this->client($account->credential('secret_key'))->post('/payments', [
            'tx_ref' => $reference,
            'amount' => $charge['major'],
            'currency' => $charge['currency'],
            'redirect_url' => $returnUrl,
            'customer' => [
                'email' => $email,
                'name' => $invoice->lead->name,
            ],
            'customizations' => [
                'title' => 'Invoice '.$invoice->number,
            ],
            'meta' => [
                'invoice_id' => (string) $invoice->id,
                'invoice_currency' => strtoupper($invoice->currency),
                'invoice_total_cents' => $invoice->total_cents,
                'fx_rate' => $charge['rate'],
            ],
        ]));

        $url = $response->json('data.link');

        if ($response->failed() || blank($url)) {
            throw new PaymentException(__('The payment page could not be opened. Please try again.'));
        }

        $invoice->forceFill([
            'pending_reference' => $reference,
            'pending_amount_cents' => $charge['amount_cents'],
            'pending_currency' => strtolower($charge['currency']),
        ])->save();

        return $url;
    }

    public function completePayment(Invoice $invoice, PaymentAccount $account, Request $request): bool
    {
        $reference = (string) ($request->query('tx_ref') ?: $invoice->pending_reference);

        if ($reference === '' || $reference !== $invoice->pending_reference) {
            return false;
        }

        $response = $this->send(fn () => $this->client($account->credential('secret_key'))
            ->get('/transactions/verify_by_reference', ['tx_ref' => $reference]));

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
        $amountCents = (int) round(((float) ($data['amount'] ?? 0)) * 100);
        $expectedAmount = (int) ($invoice->pending_amount_cents ?? $invoice->total_cents);
        $expectedCurrency = strtolower((string) ($invoice->pending_currency ?: $invoice->currency));

        $isFullPayment = strtolower((string) ($data['status'] ?? '')) === 'successful'
            && $amountCents === $expectedAmount
            && strtolower((string) ($data['currency'] ?? '')) === $expectedCurrency
            && (string) ($data['meta']['invoice_id'] ?? $data['meta']['invoiceId'] ?? '') === (string) $invoice->id;

        if (! $isFullPayment) {
            return false;
        }

        $this->markPaid->handle($invoice, 'flutterwave', (string) ($data['id'] ?? $invoice->pending_reference));

        return true;
    }

    public function refundPayment(Invoice $invoice, PaymentAccount $account): void
    {
        $response = $this->send(fn () => $this->client($account->credential('secret_key'))
            ->post('/transactions/'.$invoice->payment_reference.'/refund', [
                'amount' => number_format($invoice->total_cents / 100, 2, '.', ''),
            ]));

        if ($response->failed()) {
            throw new PaymentException((string) ($response->json('message') ?? __('Flutterwave could not refund this payment.')));
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
     * @param  Closure(): Response  $request
     */
    protected function send(Closure $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new PaymentException(__('Flutterwave could not be reached. Try again in a moment.'));
        }
    }
}

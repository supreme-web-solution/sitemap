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

class StripeGateway implements PaymentGateway
{
    public const BASE_URL = 'https://api.stripe.com/v1';

    public function __construct(protected MarkInvoicePaid $markPaid) {}

    public function verifyCredentials(array $credentials): string
    {
        $response = $this->send(fn () => $this->client($credentials['secret_key'] ?? '')->get('/account'));

        if ($response->status() === 401) {
            throw new PaymentException(__('Stripe rejected this secret key. Check that you copied the whole key.'));
        }

        if ($response->failed()) {
            throw new PaymentException(__('Stripe could not be reached. Try again in a moment.'));
        }

        return (string) ($response->json('business_profile.name') ?: $response->json('settings.dashboard.display_name') ?: $response->json('email') ?: 'Stripe account');
    }

    public function startPayment(Invoice $invoice, PaymentAccount $account, string $returnUrl, string $cancelUrl): string
    {
        $fields = [
            'mode' => 'payment',
            'success_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $invoice->id,
            'metadata' => ['invoice_id' => (string) $invoice->id, 'invoice_number' => $invoice->number],
            'payment_intent_data' => [
                'description' => 'Invoice '.$invoice->number,
                'metadata' => ['invoice_id' => (string) $invoice->id],
            ],
            'line_items' => $invoice->items->map(fn ($item) => [
                'quantity' => $item->quantity,
                'price_data' => [
                    'currency' => $invoice->currency,
                    'unit_amount' => $item->unit_cents,
                    'product_data' => ['name' => $item->description],
                ],
            ])->all(),
        ];

        if (filled($invoice->lead->email)) {
            $fields['customer_email'] = $invoice->lead->email;
        }

        $response = $this->send(fn () => $this->client($account->credential('secret_key'))->asForm()->post('/checkout/sessions', $fields));

        if ($response->failed() || blank($response->json('url'))) {
            throw new PaymentException(__('The payment page could not be opened. Please try again.'));
        }

        $invoice->forceFill(['pending_reference' => $response->json('id')])->save();

        return $response->json('url');
    }

    public function completePayment(Invoice $invoice, PaymentAccount $account, Request $request): bool
    {
        $sessionId = (string) $request->query('session_id');

        if ($sessionId === '' || $sessionId !== $invoice->pending_reference) {
            return false;
        }

        $response = $this->send(fn () => $this->client($account->credential('secret_key'))->get('/checkout/sessions/'.rawurlencode($sessionId)));

        if ($response->failed()) {
            throw new PaymentException(__('We could not confirm the payment yet. Refresh in a moment.'));
        }

        return $this->settle($invoice, $response->json());
    }

    /**
     * Mark the invoice paid when the checkout session really is a full payment for it.
     *
     * @param  array<string, mixed>  $session
     */
    public function settle(Invoice $invoice, array $session): bool
    {
        $isFullPayment = ($session['payment_status'] ?? null) === 'paid'
            && (int) ($session['amount_total'] ?? -1) === $invoice->total_cents
            && strtolower((string) ($session['currency'] ?? '')) === $invoice->currency
            && (string) ($session['metadata']['invoice_id'] ?? '') === (string) $invoice->id;

        if (! $isFullPayment) {
            return false;
        }

        $this->markPaid->handle($invoice, 'stripe', $session['payment_intent'] ?? $session['id'] ?? null);

        return true;
    }

    public function refundPayment(Invoice $invoice, PaymentAccount $account): void
    {
        $response = $this->send(fn () => $this->client($account->credential('secret_key'))->asForm()->post('/refunds', [
            'payment_intent' => $invoice->payment_reference,
        ]));

        if ($response->failed()) {
            throw new PaymentException((string) ($response->json('error.message') ?? __('Stripe could not refund this payment.')));
        }
    }

    protected function client(?string $secretKey): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withBasicAuth((string) $secretKey, '')
            ->acceptJson()
            ->timeout(20);
    }

    /**
     * Run a request, turning network failures into a message users can read.
     *
     * @param  Closure(): Response  $request
     */
    protected function send(Closure $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new PaymentException(__('Stripe could not be reached. Try again in a moment.'));
        }
    }
}

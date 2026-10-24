<?php

namespace App\Services\Payments;

use App\Actions\Invoices\MarkInvoicePaid;
use App\Enums\RecurringCollectionMode;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayPalGateway implements PaymentGateway
{
    public function __construct(protected MarkInvoicePaid $markPaid) {}

    public function verifyCredentials(array $credentials): string
    {
        $this->accessToken($credentials);

        return ($credentials['mode'] ?? 'live') === 'sandbox' ? 'PayPal sandbox' : 'PayPal';
    }

    public function startPayment(Invoice $invoice, PaymentAccount $account, string $returnUrl, string $cancelUrl): string
    {
        $invoice->loadMissing('recurringInvoice', 'lead');

        $credentials = $account->credentials;
        $amount = number_format($invoice->total_cents / 100, 2, '.', '');

        $paypalSource = [
            'experience_context' => [
                'brand_name' => Str::limit($invoice->team->name, 120, ''),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        if ($this->shouldSavePaymentMethod($invoice)) {
            $paypalSource['attributes'] = [
                'vault' => [
                    'store_in_vault' => 'ON_SUCCESS',
                    'usage_type' => 'MERCHANT',
                    'customer_type' => 'CONSUMER',
                ],
            ];
        }

        $response = $this->send(fn () => $this->request($credentials)
            ->withHeaders(['PayPal-Request-Id' => (string) Str::uuid()])
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $invoice->number,
                    'custom_id' => (string) $invoice->id,
                    'description' => Str::limit('Invoice '.$invoice->number, 120, ''),
                    'amount' => ['currency_code' => strtoupper($invoice->currency), 'value' => $amount],
                ]],
                'payment_source' => ['paypal' => $paypalSource],
            ]));

        $link = collect($response->json('links', []))->first(fn (array $link) => in_array($link['rel'] ?? '', ['payer-action', 'approve'], true));

        if ($response->failed() || blank($link['href'] ?? null) || blank($response->json('id'))) {
            throw new PaymentException(__('The payment page could not be opened. Please try again.'));
        }

        $invoice->forceFill(['pending_reference' => $response->json('id')])->save();

        return $link['href'];
    }

    public function completePayment(Invoice $invoice, PaymentAccount $account, Request $request): bool
    {
        $orderId = (string) $request->query('token');

        return $this->completeOrder($invoice, $account, $orderId);
    }

    /**
     * Capture the invoice's order if the customer approved it, and mark the invoice paid.
     *
     * Used when the customer returns to the invoice and when PayPal tells us about the order.
     */
    public function completeOrder(Invoice $invoice, PaymentAccount $account, string $orderId): bool
    {
        if ($orderId === '' || $orderId !== $invoice->pending_reference) {
            return false;
        }

        $credentials = $account->credentials;
        $path = '/v2/checkout/orders/'.rawurlencode($orderId);

        $capture = $this->send(fn () => $this->request($credentials)->withBody('{}', 'application/json')->post($path.'/capture'));
        $order = $capture->successful()
            ? $capture->json()
            : $this->send(fn () => $this->request($credentials)->get($path))->json();

        $paid = $this->settle($invoice, (array) $order);

        if ($paid) {
            $this->syncVaultFromOrder($invoice, (array) $order);
        }

        return $paid;
    }

    /**
     * Mark the invoice paid when the order really is a completed full payment for it.
     *
     * @param  array<string, mixed>  $order
     */
    public function settle(Invoice $invoice, array $order): bool
    {
        $unit = $order['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? [];
        $paid = $capture['amount'] ?? [];

        $isFullPayment = ($order['status'] ?? null) === 'COMPLETED'
            && ($capture['status'] ?? null) === 'COMPLETED'
            && (string) ($unit['custom_id'] ?? '') === (string) $invoice->id
            && strtolower((string) ($paid['currency_code'] ?? '')) === $invoice->currency
            && (int) round(((float) ($paid['value'] ?? 0)) * 100) === $invoice->total_cents;

        if (! $isFullPayment) {
            return false;
        }

        $this->markPaid->handle($invoice, 'paypal', $capture['id'] ?? $order['id'] ?? null);

        return true;
    }

    /**
     * Charge a vaulted PayPal payment method for a recurring invoice.
     */
    public function attemptOffSessionCharge(Invoice $invoice, PaymentAccount $account): bool
    {
        $invoice->loadMissing('lead');

        $lead = $invoice->lead;

        if (! $lead->hasPayPalVaultOnFile()) {
            return false;
        }

        $credentials = $account->credentials;
        $amount = number_format($invoice->total_cents / 100, 2, '.', '');

        $response = $this->send(fn () => $this->request($credentials)
            ->withHeaders(['PayPal-Request-Id' => (string) Str::uuid()])
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $invoice->number,
                    'custom_id' => (string) $invoice->id,
                    'description' => Str::limit('Invoice '.$invoice->number, 120, ''),
                    'amount' => ['currency_code' => strtoupper($invoice->currency), 'value' => $amount],
                ]],
                'payment_source' => [
                    'token' => [
                        'id' => $lead->paypal_vault_id,
                        'type' => 'PAYMENT_METHOD_TOKEN',
                    ],
                ],
            ]));

        if ($response->failed()) {
            return false;
        }

        $order = (array) $response->json();

        if (($order['status'] ?? '') !== 'COMPLETED') {
            return false;
        }

        return $this->settle($invoice, $order);
    }

    /**
     * Keep the lead's vaulted PayPal token after Checkout when auto-charge is enabled.
     *
     * @param  array<string, mixed>  $order
     */
    public function syncVaultFromOrder(Invoice $invoice, array $order): void
    {
        if (! $this->shouldSavePaymentMethod($invoice)) {
            return;
        }

        $vaultId = $order['payment_source']['paypal']['attributes']['vault']['id'] ?? null;

        if (! is_string($vaultId) || $vaultId === '') {
            return;
        }

        $invoice->lead->forceFill(['paypal_vault_id' => $vaultId])->save();
    }

    public function refundPayment(Invoice $invoice, PaymentAccount $account): void
    {
        if (blank($invoice->payment_reference)) {
            throw new PaymentException(__('This PayPal payment has no capture id to refund.'));
        }

        $response = $this->send(fn () => $this->request($account->credentials)
            ->post('/v2/payments/captures/'.$invoice->payment_reference.'/refund', [
                'amount' => [
                    'currency_code' => strtoupper($invoice->currency),
                    'value' => number_format($invoice->total_cents / 100, 2, '.', ''),
                ],
            ]));

        if ($response->failed()) {
            throw new PaymentException(__('PayPal could not refund this payment.'));
        }
    }

    /**
     * Ask PayPal whether a webhook call really came from PayPal for this account.
     */
    public function hasValidWebhookSignature(PaymentAccount $account, Request $request): bool
    {
        $webhookId = $account->credential('webhook_id');
        $headers = [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        if ($webhookId === null || in_array(null, $headers, true) || in_array('', $headers, true)) {
            return false;
        }

        $body = '{'.collect($headers + ['webhook_id' => $webhookId])
            ->map(fn ($value, $key) => json_encode($key).':'.json_encode($value))
            ->implode(',').',"webhook_event":'.$request->getContent().'}';

        try {
            $response = $this->send(fn () => $this->request($account->credentials)
                ->withBody($body, 'application/json')
                ->post('/v1/notifications/verify-webhook-signature'));
        } catch (PaymentException) {
            return false;
        }

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    /**
     * @param  array<string, string>  $credentials
     */
    protected function request(array $credentials): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($credentials))
            ->withToken($this->accessToken($credentials))
            ->acceptJson()
            ->timeout(25);
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
            throw new PaymentException(__('PayPal could not be reached. Try again in a moment.'));
        }
    }

    /**
     * @param  array<string, string>  $credentials
     */
    protected function accessToken(array $credentials): string
    {
        try {
            $response = Http::baseUrl($this->baseUrl($credentials))
                ->withBasicAuth($credentials['client_id'] ?? '', $credentials['client_secret'] ?? '')
                ->asForm()
                ->acceptJson()
                ->timeout(20)
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException) {
            throw new PaymentException(__('PayPal could not be reached. Try again in a moment.'));
        }

        if (in_array($response->status(), [400, 401], true)) {
            throw new PaymentException(__('PayPal rejected these credentials. Check the client ID, secret and mode.'));
        }

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new PaymentException(__('PayPal could not be reached. Try again in a moment.'));
        }

        return $response->json('access_token');
    }

    /**
     * @param  array<string, string>  $credentials
     */
    protected function baseUrl(array $credentials): string
    {
        return ($credentials['mode'] ?? 'live') === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    protected function shouldSavePaymentMethod(Invoice $invoice): bool
    {
        $schedule = $invoice->recurringInvoice;

        return $schedule !== null
            && $schedule->collection_mode === RecurringCollectionMode::AutoCharge
            && $schedule->autoCharges();
    }
}

<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\PaymentAccount;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * @return array{0: Invoice, 1: PaymentAccount}
 */
function payableInvoice(bool $paypal = false, array $attributes = []): array
{
    $team = Team::factory()->create();
    $lead = Lead::factory()->for($team)->create(['email' => 'client@example.com', 'status' => LeadStatus::Interested]);
    $invoice = Invoice::factory()->create(['lead_id' => $lead->id, 'total_cents' => 150000, ...$attributes]);
    $account = $paypal
        ? PaymentAccount::factory()->paypal()->for($team)->create()
        : PaymentAccount::factory()->for($team)->create();

    return [$invoice, $account];
}

/**
 * A signed Stripe webhook call for the given event.
 *
 * @param  array<string, mixed>  $event
 * @return array{0: string, 1: array<string, string>}
 */
function signedStripeEvent(PaymentAccount $account, array $event, ?string $secret = null, ?int $timestamp = null): array
{
    $payload = json_encode($event);
    $timestamp ??= time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret ?? $account->credential('webhook_secret'));

    return [$payload, ['Stripe-Signature' => "t={$timestamp},v1={$signature}", 'Content-Type' => 'application/json']];
}

/**
 * @return array<string, mixed>
 */
function paidSession(Invoice $invoice, array $overrides = []): array
{
    return [
        'id' => 'cs_test_1',
        'payment_status' => 'paid',
        'amount_total' => $invoice->total_cents,
        'currency' => 'usd',
        'payment_intent' => 'pi_123',
        'metadata' => ['invoice_id' => (string) $invoice->id],
        ...$overrides,
    ];
}

test('the public invoice page shows the invoice and only the connected providers', function () {
    [$invoice] = payableInvoice();

    $this->get(route('invoices.public', $invoice->public_token))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee($invoice->number)
        ->assertSee('$1,500.00')
        ->assertSee('Pay by card')
        ->assertDontSee('PayPal');
});

test('a draft invoice is not visible to the public', function () {
    $invoice = Invoice::factory()->draft()->create();

    $this->get(route('invoices.public', $invoice->public_token))->assertNotFound();
    $this->get('/i/not-a-real-token')->assertNotFound();
});

test('a paid invoice shows a receipt and no pay buttons', function () {
    [$invoice] = payableInvoice(attributes: ['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

    $this->get(route('invoices.public', $invoice->public_token))
        ->assertOk()
        ->assertSee('Payment received')
        ->assertDontSee('Pay by card');
});

test('starting a stripe payment redirects to checkout and remembers the session', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
    [$invoice] = payableInvoice();

    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'stripe']))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

    expect($invoice->fresh()->pending_reference)->toBe('cs_test_1');

    Http::assertSent(fn ($request) => $request['mode'] === 'payment'
        && $request['line_items'][0]['price_data']['unit_amount'] === 150000
        && $request['line_items'][0]['price_data']['currency'] === 'usd'
        && $request['metadata']['invoice_id'] === (string) $invoice->id);
});

test('a stripe failure sends the customer back with an error', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['error' => 'nope'], 500)]);
    [$invoice] = payableInvoice();

    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'stripe']))
        ->assertRedirect(route('invoices.public', ['invoice' => $invoice->public_token, 'status' => 'error']));
});

test('a provider that is not connected, or an unpayable invoice, cannot start a payment', function () {
    Http::fake();
    [$invoice] = payableInvoice();
    $voided = Invoice::factory()->create(['status' => InvoiceStatus::Void]);

    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'paypal']))->assertNotFound();
    $this->post(route('invoices.public.pay', ['invoice' => $voided->public_token, 'provider' => 'stripe']))->assertNotFound();
    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'bitcoin']))->assertNotFound();

    Http::assertNothingSent();
});

test('returning from a paid stripe checkout marks the invoice paid and wins the lead', function () {
    [$invoice] = payableInvoice(attributes: ['pending_reference' => 'cs_test_1']);
    Http::fake(['api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(paidSession($invoice))]);

    $this->get(route('invoices.public.return', ['invoice' => $invoice->public_token, 'provider' => 'stripe', 'session_id' => 'cs_test_1']))
        ->assertRedirect(route('invoices.public', ['invoice' => $invoice->public_token, 'status' => 'paid']));

    $fresh = $invoice->fresh();

    expect($fresh->status)->toBe(InvoiceStatus::Paid)
        ->and($fresh->paid_via)->toBe('stripe')
        ->and($fresh->payment_reference)->toBe('pi_123')
        ->and($fresh->lead->status)->toBe(LeadStatus::Won)
        ->and($fresh->lead->activities()->where('type', LeadActivityType::InvoicePaid)->count())->toBe(1);
});

test('a return with a session that is not this invoices is ignored', function () {
    Http::fake();
    [$invoice] = payableInvoice(attributes: ['pending_reference' => 'cs_test_1']);

    $this->get(route('invoices.public.return', ['invoice' => $invoice->public_token, 'provider' => 'stripe', 'session_id' => 'cs_other']))
        ->assertRedirect(route('invoices.public', ['invoice' => $invoice->public_token, 'status' => 'pending']));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
    Http::assertNothingSent();
});

test('a stripe session that is unpaid or for another amount does not mark the invoice paid', function (array $override) {
    [$invoice] = payableInvoice(attributes: ['pending_reference' => 'cs_test_1']);
    Http::fake(['api.stripe.com/*' => Http::response(paidSession($invoice, $override))]);

    $this->get(route('invoices.public.return', ['invoice' => $invoice->public_token, 'provider' => 'stripe', 'session_id' => 'cs_test_1']))
        ->assertRedirect(route('invoices.public', ['invoice' => $invoice->public_token, 'status' => 'pending']));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
})->with([
    'unpaid' => [['payment_status' => 'unpaid']],
    'wrong amount' => [['amount_total' => 100]],
    'wrong currency' => [['currency' => 'eur']],
    'another invoice' => [['metadata' => ['invoice_id' => '999999']]],
]);

test('a valid stripe webhook marks the invoice paid and is safe to repeat', function () {
    [$invoice, $account] = payableInvoice();
    [$payload, $headers] = signedStripeEvent($account, ['type' => 'checkout.session.completed', 'data' => ['object' => paidSession($invoice)]]);

    $this->call('POST', route('webhooks.stripe', $account->webhook_key), [], [], [], collect($headers)->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])->all(), $payload)
        ->assertOk();
    $this->call('POST', route('webhooks.stripe', $account->webhook_key), [], [], [], collect($headers)->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])->all(), $payload)
        ->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->lead->activities()->where('type', LeadActivityType::InvoicePaid)->count())->toBe(1);
});

test('stripe webhooks with a bad, stale or missing signature are rejected', function (string $case) {
    [$invoice, $account] = payableInvoice();
    $event = ['type' => 'checkout.session.completed', 'data' => ['object' => paidSession($invoice)]];

    [$payload, $headers] = match ($case) {
        'wrong secret' => signedStripeEvent($account, $event, 'whsec_other'),
        'stale' => signedStripeEvent($account, $event, null, time() - 3600),
        'missing' => [json_encode($event), ['Content-Type' => 'application/json']],
    };

    $server = collect($headers)->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])->all();

    $this->call('POST', route('webhooks.stripe', $account->webhook_key), [], [], [], $server, $payload)->assertStatus(400);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
})->with(['wrong secret', 'stale', 'missing']);

test('a stripe webhook for an unknown key is a 404 and one for another teams invoice is ignored', function () {
    [$invoice] = payableInvoice();
    [, $otherAccount] = payableInvoice();
    [$payload, $headers] = signedStripeEvent($otherAccount, ['type' => 'checkout.session.completed', 'data' => ['object' => paidSession($invoice)]]);
    $server = collect($headers)->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])->all();

    $this->call('POST', route('webhooks.stripe', 'unknown-key'), [], [], [], $server, $payload)->assertNotFound();
    $this->call('POST', route('webhooks.stripe', $otherAccount->webhook_key), [], [], [], $server, $payload)->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
});

test('starting a paypal payment creates an order and redirects to approval', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'ORDER-1',
            'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1']],
        ]),
    ]);
    [$invoice] = payableInvoice(paypal: true);

    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'paypal']))
        ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1');

    expect($invoice->fresh()->pending_reference)->toBe('ORDER-1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/checkout/orders')
        && $request['purchase_units'][0]['amount']['value'] === '1500.00'
        && $request['purchase_units'][0]['amount']['currency_code'] === 'USD');
});

test('returning from paypal captures the order and marks the invoice paid', function () {
    [$invoice] = payableInvoice(paypal: true, attributes: ['pending_reference' => 'ORDER-1']);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1/capture' => Http::response([
            'id' => 'ORDER-1',
            'status' => 'COMPLETED',
            'purchase_units' => [['custom_id' => (string) $invoice->id, 'payments' => ['captures' => [['id' => 'CAP-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '1500.00']]]]]],
        ], 201),
    ]);

    $this->get(route('invoices.public.return', ['invoice' => $invoice->public_token, 'provider' => 'paypal', 'token' => 'ORDER-1']))
        ->assertRedirect(route('invoices.public', ['invoice' => $invoice->public_token, 'status' => 'paid']));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_via)->toBe('paypal')
        ->and($invoice->fresh()->payment_reference)->toBe('CAP-1');
});

test('a failed paypal capture leaves the invoice open', function () {
    [$invoice] = payableInvoice(paypal: true, attributes: ['pending_reference' => 'ORDER-1']);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1/capture' => Http::response(['name' => 'UNPROCESSABLE_ENTITY'], 422),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1' => Http::response(['id' => 'ORDER-1', 'status' => 'APPROVED']),
    ]);

    $this->get(route('invoices.public.return', ['invoice' => $invoice->public_token, 'provider' => 'paypal', 'token' => 'ORDER-1']))
        ->assertRedirect(route('invoices.public', ['invoice' => $invoice->public_token, 'status' => 'pending']));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
});

test('a paid invoice cannot be paid again or voided by a late webhook', function () {
    [$invoice, $account] = payableInvoice(attributes: ['status' => InvoiceStatus::Void]);
    [$payload, $headers] = signedStripeEvent($account, ['type' => 'checkout.session.completed', 'data' => ['object' => paidSession($invoice)]]);
    $server = collect($headers)->mapWithKeys(fn ($value, $key) => ['HTTP_'.strtoupper(str_replace('-', '_', $key)) => $value])->all();

    $this->call('POST', route('webhooks.stripe', $account->webhook_key), [], [], [], $server, $payload)->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

/**
 * A PayPal webhook call with the headers PayPal signs.
 *
 * @param  array<string, mixed>  $event
 */
function paypalWebhookCall(PaymentAccount $account, array $event): TestResponse
{
    return test()->call('POST', route('webhooks.paypal', $account->webhook_key), [], [], [], [
        'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
        'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert.pem',
        'HTTP_PAYPAL_TRANSMISSION_ID' => 'tx-1',
        'HTTP_PAYPAL_TRANSMISSION_SIG' => 'sig',
        'HTTP_PAYPAL_TRANSMISSION_TIME' => '2026-10-23T10:00:00Z',
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($event));
}

/**
 * @return array<string, mixed>
 */
function completedPayPalOrder(Invoice $invoice): array
{
    return [
        'id' => 'ORDER-1',
        'status' => 'COMPLETED',
        'purchase_units' => [['custom_id' => (string) $invoice->id, 'payments' => ['captures' => [['id' => 'CAP-1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '1500.00']]]]]],
    ];
}

test('a verified paypal approval webhook captures the order even if the customer never came back', function () {
    [$invoice, $account] = payableInvoice(paypal: true, attributes: ['pending_reference' => 'ORDER-1']);
    $account->update(['credentials' => [...$account->credentials, 'webhook_id' => 'WH-1']]);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1/capture' => Http::response(completedPayPalOrder($invoice), 201),
    ]);

    paypalWebhookCall($account, ['event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'ORDER-1']])->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)->and($invoice->fresh()->paid_via)->toBe('paypal');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/verify-webhook-signature')
        && $request['webhook_id'] === 'WH-1'
        && $request['transmission_id'] === 'tx-1'
        && $request['webhook_event']['event_type'] === 'CHECKOUT.ORDER.APPROVED');

    paypalWebhookCall($account, ['event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'ORDER-1']])->assertOk();

    expect($invoice->lead->activities()->where('type', LeadActivityType::InvoicePaid)->count())->toBe(1);
});

test('a paypal capture completed webhook settles the invoice from the order', function () {
    [$invoice, $account] = payableInvoice(paypal: true, attributes: ['pending_reference' => 'ORDER-1']);
    $account->update(['credentials' => [...$account->credentials, 'webhook_id' => 'WH-1']]);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1/capture' => Http::response(['name' => 'ORDER_ALREADY_CAPTURED'], 422),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1' => Http::response(completedPayPalOrder($invoice)),
    ]);

    paypalWebhookCall($account, ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'CAP-1', 'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-1']]]])->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)->and($invoice->fresh()->payment_reference)->toBe('CAP-1');
});

test('paypal webhooks that fail verification or lack a webhook id are rejected', function () {
    [$invoice, $account] = payableInvoice(paypal: true, attributes: ['pending_reference' => 'ORDER-1']);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
    ]);
    $event = ['event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'ORDER-1']];

    paypalWebhookCall($account, $event)->assertStatus(400);

    $account->update(['credentials' => [...$account->credentials, 'webhook_id' => 'WH-1']]);
    paypalWebhookCall($account, $event)->assertStatus(400);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
    $this->post(route('webhooks.paypal', 'unknown-key'))->assertNotFound();
});

test('a paypal webhook for another teams order is ignored', function () {
    [$invoice] = payableInvoice(paypal: true, attributes: ['pending_reference' => 'ORDER-1']);
    [, $otherAccount] = payableInvoice(paypal: true);
    $otherAccount->update(['credentials' => [...$otherAccount->credentials, 'webhook_id' => 'WH-1']]);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
    ]);

    paypalWebhookCall($otherAccount, ['event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'ORDER-1']])->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
});

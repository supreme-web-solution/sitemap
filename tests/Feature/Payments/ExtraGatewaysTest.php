<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentProvider;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\PaymentAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * @return array{0: Invoice, 1: PaymentAccount}
 */
function extraGatewayInvoice(string $provider): array
{
    $team = Team::factory()->create();
    $lead = Lead::factory()->for($team)->create(['email' => 'client@example.com', 'status' => LeadStatus::Interested]);
    $invoice = Invoice::factory()->create(['lead_id' => $lead->id, 'total_cents' => 150000]);
    $account = $provider === 'flutterwave'
        ? PaymentAccount::factory()->flutterwave()->for($team)->create()
        : PaymentAccount::factory()->paystack()->for($team)->create();

    return [$invoice, $account];
}

test('paystack is connected after the secret key is verified', function () {
    Http::fake(['api.paystack.co/balance' => Http::response(['status' => true])]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('payments.connect', ['current_team' => $user->currentTeam->slug, 'provider' => 'paystack']), [
            'secret_key' => 'sk_test_paystack',
        ])
        ->assertSessionHasNoErrors();

    expect($user->currentTeam->paymentAccounts()->sole()->provider)->toBe(PaymentProvider::Paystack);
});

test('flutterwave is connected after the secret key is verified', function () {
    Http::fake(['api.flutterwave.com/v3/balances' => Http::response(['status' => 'success'])]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('payments.connect', ['current_team' => $user->currentTeam->slug, 'provider' => 'flutterwave']), [
            'secret_key' => 'FLWSECK_TEST-secret',
        ])
        ->assertSessionHasNoErrors();

    expect($user->currentTeam->paymentAccounts()->sole()->provider)->toBe(PaymentProvider::Flutterwave);
});

test('starting a paystack payment redirects to the authorization url', function () {
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
        'status' => true,
        'data' => ['authorization_url' => 'https://checkout.paystack.com/abc'],
    ])]);
    [$invoice] = extraGatewayInvoice('paystack');

    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'paystack']))
        ->assertRedirect('https://checkout.paystack.com/abc');

    expect($invoice->fresh()->pending_reference)->toStartWith('inv-'.$invoice->id.'-');
});

test('a signed paystack webhook marks the invoice paid and is safe to repeat', function () {
    [$invoice, $account] = extraGatewayInvoice('paystack');
    $payload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'status' => 'success',
            'amount' => 150000,
            'currency' => 'usd',
            'reference' => 'psk_ref',
            'metadata' => ['invoice_id' => (string) $invoice->id],
        ],
    ]);
    $headers = [
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, $account->credential('secret_key')),
        'CONTENT_TYPE' => 'application/json',
    ];

    $this->call('POST', route('webhooks.paystack', $account->webhook_key), [], [], [], $headers, $payload)->assertOk();
    $this->call('POST', route('webhooks.paystack', $account->webhook_key), [], [], [], $headers, $payload)->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_via)->toBe('paystack');
});

test('paystack webhooks with a bad signature are rejected', function () {
    [$invoice, $account] = extraGatewayInvoice('paystack');
    $payload = json_encode(['event' => 'charge.success', 'data' => ['metadata' => ['invoice_id' => (string) $invoice->id]]]);

    $this->call('POST', route('webhooks.paystack', $account->webhook_key), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => 'nope',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(400);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
});

test('a signed flutterwave webhook marks the invoice paid', function () {
    [$invoice, $account] = extraGatewayInvoice('flutterwave');
    $payload = json_encode([
        'data' => [
            'status' => 'successful',
            'amount' => 1500,
            'currency' => 'usd',
            'id' => 'flw_1',
            'meta' => ['invoice_id' => (string) $invoice->id],
        ],
    ]);

    $this->call('POST', route('webhooks.flutterwave', $account->webhook_key), [], [], [], [
        'HTTP_VERIF_HASH' => $account->credential('webhook_hash'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_via)->toBe('flutterwave');
});

test('a flutterwave webhook for another teams invoice is ignored', function () {
    [$invoice] = extraGatewayInvoice('paystack');
    [, $otherAccount] = extraGatewayInvoice('flutterwave');
    $payload = json_encode([
        'data' => [
            'status' => 'successful',
            'amount' => 1500,
            'currency' => 'usd',
            'id' => 'flw_other',
            'meta' => ['invoice_id' => (string) $invoice->id],
        ],
    ]);

    $this->call('POST', route('webhooks.flutterwave', $otherAccount->webhook_key), [], [], [], [
        'HTTP_VERIF_HASH' => $otherAccount->credential('webhook_hash'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
});

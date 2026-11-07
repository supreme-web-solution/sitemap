<?php

use App\Enums\LeadStatus;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\PaymentAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * @return array{0: Invoice, 1: PaymentAccount}
 */
function localCurrencyInvoice(string $provider): array
{
    $team = Team::factory()->create();
    $lead = Lead::factory()->for($team)->create([
        'email' => 'client@example.com',
        'status' => LeadStatus::Interested,
    ]);
    $invoice = Invoice::factory()->create([
        'lead_id' => $lead->id,
        'total_cents' => 19900,
        'currency' => 'usd',
    ]);
    $account = $provider === 'flutterwave'
        ? PaymentAccount::factory()->flutterwave()->for($team)->create()
        : PaymentAccount::factory()->paystack()->for($team)->create();

    return [$invoice, $account];
}

test('paystack charges usd invoices in ngn using the live exchange rate', function () {
    Http::fake([
        'open.er-api.com/*' => Http::response([
            'result' => 'success',
            'time_last_update_utc' => 'Sat, 07 Nov 2026 00:00:00 +0000',
            'rates' => ['NGN' => 1500, 'USD' => 1],
        ]),
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.com/ngn'],
        ]),
    ]);

    [$invoice] = localCurrencyInvoice('paystack');

    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'paystack']))
        ->assertRedirect('https://checkout.paystack.com/ngn');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.paystack.co/transaction/initialize')
        && $request['currency'] === 'NGN'
        && (int) $request['amount'] === 29850000);

    expect($invoice->fresh())
        ->pending_currency->toBe('ngn')
        ->pending_amount_cents->toBe(29850000);
});

test('flutterwave charges usd invoices in ngn using the live exchange rate', function () {
    Http::fake([
        'open.er-api.com/*' => Http::response([
            'result' => 'success',
            'time_last_update_utc' => 'Sat, 07 Nov 2026 00:00:00 +0000',
            'rates' => ['NGN' => 1500, 'USD' => 1],
        ]),
        'api.flutterwave.com/v3/payments' => Http::response([
            'status' => 'success',
            'data' => ['link' => 'https://checkout.flutterwave.com/ngn'],
        ]),
    ]);

    [$invoice] = localCurrencyInvoice('flutterwave');

    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'flutterwave']))
        ->assertRedirect('https://checkout.flutterwave.com/ngn');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.flutterwave.com/v3/payments')
        && $request['currency'] === 'NGN'
        && $request['amount'] === '298500.00');

    expect($invoice->fresh())
        ->pending_currency->toBe('ngn')
        ->pending_amount_cents->toBe(29850000);
});

test('the payments page shows the live usd to ngn rate on african gateways', function () {
    $user = User::factory()->create();

    Http::fake([
        'open.er-api.com/*' => Http::response([
            'result' => 'success',
            'time_last_update_utc' => 'Sat, 07 Nov 2026 00:00:00 +0000',
            'rates' => ['NGN' => 1348.11, 'USD' => 1],
        ]),
    ]);

    $this->actingAs($user)
        ->get(route('payments.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('payments/Index')
            ->where('providers.2.value', 'paystack')
            ->where('providers.2.localCheckout.formatted', '1 USD ≈ 1,348.11 NGN')
            ->where('providers.3.value', 'flutterwave')
            ->where('providers.3.localCheckout.to', 'NGN'));
});

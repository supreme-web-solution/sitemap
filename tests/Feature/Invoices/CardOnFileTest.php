<?php

use App\Enums\InvoiceStatus;
use App\Enums\RecurringCollectionMode;
use App\Mail\InvoiceEmail;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\PaymentAccount;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

test('a recurring schedule can charge a saved stripe card without emailing', function () {
    Mail::fake();
    Http::fake([
        'api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_auto_1', 'status' => 'succeeded']),
    ]);

    $lead = Lead::factory()->create([
        'email' => 'client@example.com',
        'stripe_customer_id' => 'cus_test',
        'stripe_payment_method_id' => 'pm_test',
    ]);
    PaymentAccount::factory()->for($lead->team)->create();
    RecurringInvoice::factory()->create([
        'lead_id' => $lead->id,
        'collection_mode' => RecurringCollectionMode::AutoCharge,
        'payment_provider' => 'stripe',
    ]);

    $this->artisan('invoices:run-recurring')->assertSuccessful();

    $invoice = Invoice::sole();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_via)->toBe('stripe')
        ->and($invoice->emailed_at)->toBeNull();

    Mail::assertNothingQueued();
});

test('auto charge falls back to emailing when the card charge fails', function () {
    Mail::fake();
    Http::fake([
        'api.stripe.com/v1/payment_intents' => Http::response(['error' => ['message' => 'declined']], 402),
    ]);

    $lead = Lead::factory()->create([
        'email' => 'client@example.com',
        'stripe_customer_id' => 'cus_test',
        'stripe_payment_method_id' => 'pm_test',
    ]);
    PaymentAccount::factory()->for($lead->team)->create();
    RecurringInvoice::factory()->create([
        'lead_id' => $lead->id,
        'collection_mode' => RecurringCollectionMode::AutoCharge,
        'payment_provider' => 'stripe',
    ]);

    $this->artisan('invoices:run-recurring')->assertSuccessful();

    expect(Invoice::sole()->status)->toBe(InvoiceStatus::Open);
    Mail::assertQueued(InvoiceEmail::class);
});

test('paying a recurring invoice through stripe can save the card for later cycles', function () {
    $lead = Lead::factory()->create(['email' => 'client@example.com']);
    PaymentAccount::factory()->for($lead->team)->create();
    $schedule = RecurringInvoice::factory()->create([
        'lead_id' => $lead->id,
        'collection_mode' => RecurringCollectionMode::AutoCharge,
        'payment_provider' => 'stripe',
    ]);
    $invoice = Invoice::factory()->create([
        'lead_id' => $lead->id,
        'total_cents' => 5000,
        'recurring_invoice_id' => $schedule->id,
        'pending_reference' => 'cs_save_1',
    ]);
    $invoice->items()->create(['description' => 'Hosting', 'quantity' => 1, 'unit_cents' => 5000]);

    Http::fake([
        'api.stripe.com/v1/checkout/sessions/cs_save_1' => Http::response([
            'id' => 'cs_save_1',
            'mode' => 'payment',
            'payment_status' => 'paid',
            'amount_total' => 5000,
            'currency' => 'usd',
            'payment_intent' => 'pi_save_1',
            'customer' => 'cus_save_1',
            'metadata' => ['invoice_id' => (string) $invoice->id],
        ]),
        'api.stripe.com/v1/payment_intents/pi_save_1' => Http::response(['payment_method' => 'pm_save_1']),
    ]);

    $this->get(route('invoices.public.return', [
        'invoice' => $invoice->public_token,
        'provider' => 'stripe',
        'session_id' => 'cs_save_1',
    ]))->assertRedirect();

    $lead->refresh();

    expect($lead->stripe_customer_id)->toBe('cus_save_1')
        ->and($lead->stripe_payment_method_id)->toBe('pm_save_1')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('stripe checkout for auto charge schedules saves the card for off session use', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_save_1', 'url' => 'https://checkout.stripe.com/save'])]);

    $lead = Lead::factory()->create(['email' => 'client@example.com']);
    PaymentAccount::factory()->for($lead->team)->create();
    $schedule = RecurringInvoice::factory()->create([
        'lead_id' => $lead->id,
        'collection_mode' => RecurringCollectionMode::AutoCharge,
        'payment_provider' => 'stripe',
    ]);
    $invoice = Invoice::factory()->create([
        'lead_id' => $lead->id,
        'total_cents' => 5000,
        'recurring_invoice_id' => $schedule->id,
    ]);
    $invoice->items()->create(['description' => 'Hosting', 'quantity' => 1, 'unit_cents' => 5000]);

    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'stripe']))
        ->assertRedirect('https://checkout.stripe.com/save');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
        && ($request['payment_intent_data']['setup_future_usage'] ?? null) === 'off_session');
});

test('a recurring schedule can charge a vaulted paypal payment method without emailing', function () {
    Mail::fake();
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/v1/oauth2/token')) {
            return Http::response(['access_token' => 'token']);
        }

        if ($request->method() === 'POST' && str_ends_with($request->url(), '/v2/checkout/orders')) {
            $customId = (string) ($request->data()['purchase_units'][0]['custom_id'] ?? '');

            return Http::response([
                'id' => 'ORDER-AUTO',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'custom_id' => $customId,
                    'payments' => ['captures' => [[
                        'id' => 'CAP-AUTO',
                        'status' => 'COMPLETED',
                        'amount' => ['currency_code' => 'USD', 'value' => '50.00'],
                    ]]],
                ]],
            ]);
        }
    });

    $lead = Lead::factory()->create([
        'email' => 'client@example.com',
        'paypal_vault_id' => 'VAULT-1',
    ]);
    PaymentAccount::factory()->paypal()->for($lead->team)->create();
    RecurringInvoice::factory()->create([
        'lead_id' => $lead->id,
        'collection_mode' => RecurringCollectionMode::AutoCharge,
        'payment_provider' => 'paypal',
    ]);

    $this->artisan('invoices:run-recurring')->assertSuccessful();

    $invoice = Invoice::sole();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_via)->toBe('paypal')
        ->and($invoice->emailed_at)->toBeNull();

    Mail::assertNothingQueued();
});

test('paying a recurring invoice through paypal can vault the payment method for later cycles', function () {
    $lead = Lead::factory()->create(['email' => 'client@example.com']);
    PaymentAccount::factory()->paypal()->for($lead->team)->create();
    $schedule = RecurringInvoice::factory()->create([
        'lead_id' => $lead->id,
        'collection_mode' => RecurringCollectionMode::AutoCharge,
        'payment_provider' => 'paypal',
    ]);
    $invoice = Invoice::factory()->create([
        'lead_id' => $lead->id,
        'total_cents' => 5000,
        'recurring_invoice_id' => $schedule->id,
        'pending_reference' => 'ORDER-VAULT',
    ]);
    $invoice->items()->create(['description' => 'Hosting', 'quantity' => 1, 'unit_cents' => 5000]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-VAULT/capture' => Http::response([
            'id' => 'ORDER-VAULT',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'custom_id' => (string) $invoice->id,
                'payments' => ['captures' => [[
                    'id' => 'CAP-VAULT',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'USD', 'value' => '50.00'],
                ]]],
            ]],
            'payment_source' => [
                'paypal' => [
                    'attributes' => [
                        'vault' => ['id' => 'VAULT-SAVE-1'],
                    ],
                ],
            ],
        ], 201),
    ]);

    $this->get(route('invoices.public.return', [
        'invoice' => $invoice->public_token,
        'provider' => 'paypal',
        'token' => 'ORDER-VAULT',
    ]))->assertRedirect();

    $lead->refresh();

    expect($lead->paypal_vault_id)->toBe('VAULT-SAVE-1')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('auto charge schedules require stripe to be connected', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->from(route('recurring-invoices.index', ['current_team' => $user->currentTeam->slug]))
        ->post(route('recurring-invoices.store', ['current_team' => $user->currentTeam->slug]), [
            'lead_id' => $lead->id,
            'interval' => 'monthly',
            'starts_on' => today()->addDay()->toDateString(),
            'due_in_days' => 14,
            'email_client' => true,
            'collection_mode' => 'auto_charge',
            'payment_provider' => 'stripe',
            'items' => [['description' => 'Hosting', 'quantity' => 1, 'unit_price' => '50']],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('collection_mode');
});

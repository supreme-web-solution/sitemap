<?php

use App\Enums\PaymentProvider;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\PaymentAccount;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('a newly connected provider stays off invoices until enabled', function () {
    Http::fake(['api.stripe.com/v1/account' => Http::response(['business_profile' => ['name' => 'Acme Agency']])]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('payments.connect', ['current_team' => $user->currentTeam->slug, 'provider' => 'stripe']), [
            'secret_key' => 'sk_test_abc123XYZ',
        ])
        ->assertSessionHasNoErrors();

    $account = $user->currentTeam->paymentAccounts()->sole();

    expect($account->enabled)->toBeFalse();

    $lead = Lead::factory()->for($user->currentTeam)->create();
    $invoice = Invoice::factory()->create(['lead_id' => $lead->id]);

    $this->get(route('invoices.public', $invoice->public_token))
        ->assertOk()
        ->assertDontSee('Pay by card')
        ->assertSee('Online payment is not available');
});

test('enabling a connected provider offers it on the public invoice', function () {
    $user = User::factory()->create();
    PaymentAccount::factory()->disabled()->for($user->currentTeam)->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $invoice = Invoice::factory()->create(['lead_id' => $lead->id]);

    $this->get(route('invoices.public', $invoice->public_token))
        ->assertDontSee('Pay by card');

    $this->actingAs($user)
        ->patch(route('payments.update', ['current_team' => $user->currentTeam->slug, 'provider' => 'stripe']), [
            'enabled' => true,
        ])
        ->assertRedirect();

    expect($user->currentTeam->paymentAccounts()->sole()->enabled)->toBeTrue();

    $this->get(route('invoices.public', $invoice->public_token))
        ->assertOk()
        ->assertSee('Pay by card');
});

test('disabled providers cannot start a payment', function () {
    Http::fake();
    $team = Team::factory()->create();
    PaymentAccount::factory()->disabled()->for($team)->create();
    $lead = Lead::factory()->for($team)->create();
    $invoice = Invoice::factory()->create(['lead_id' => $lead->id]);

    $this->post(route('invoices.public.pay', ['invoice' => $invoice->public_token, 'provider' => 'stripe']))
        ->assertNotFound();

    Http::assertNothingSent();
});

test('two enabled providers render side by side on the public invoice', function () {
    $team = Team::factory()->create();
    PaymentAccount::factory()->for($team)->create();
    PaymentAccount::factory()->paypal()->for($team)->create();
    $lead = Lead::factory()->for($team)->create();
    $invoice = Invoice::factory()->create(['lead_id' => $lead->id]);

    $this->get(route('invoices.public', $invoice->public_token))
        ->assertOk()
        ->assertSee('count-2', false)
        ->assertSee('Pay by card')
        ->assertSee('Pay with PayPal or card')
        ->assertSee('PayPal account optional');
});

test('the payments page exposes the enabled flag', function () {
    $user = User::factory()->create();
    PaymentAccount::factory()->paypal()->for($user->currentTeam)->create(['enabled' => false]);

    $this->actingAs($user)
        ->get(route('payments.index', ['current_team' => $user->currentTeam->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/Index')
            ->where('providers', fn ($providers) => collect($providers)->firstWhere('value', PaymentProvider::PayPal->value)['enabled'] === false
                && collect($providers)->firstWhere('value', PaymentProvider::PayPal->value)['connected'] === true));
});

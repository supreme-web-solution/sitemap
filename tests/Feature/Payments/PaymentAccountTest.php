<?php

use App\Enums\PaymentProvider;
use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('a stripe account is connected once its key is verified', function () {
    Http::fake(['api.stripe.com/v1/account' => Http::response(['business_profile' => ['name' => 'Acme Agency']])]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('payments.connect', ['current_team' => $user->currentTeam->slug, 'provider' => 'stripe']), [
            'secret_key' => 'sk_test_abc123XYZ',
            'webhook_secret' => 'whsec_abc123',
        ])
        ->assertSessionHasNoErrors();

    $account = $user->currentTeam->paymentAccounts()->sole();

    expect($account->provider)->toBe(PaymentProvider::Stripe)
        ->and($account->label)->toBe('Acme Agency')
        ->and($account->webhook_key)->toHaveLength(32)
        ->and($account->credential('secret_key'))->toBe('sk_test_abc123XYZ');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization') && str_starts_with($request->header('Authorization')[0], 'Basic '));
});

test('credentials are encrypted at rest and never sent to the browser', function () {
    $user = User::factory()->create();
    PaymentAccount::factory()->for($user->currentTeam)->create(['credentials' => ['secret_key' => 'sk_test_supersecretvalue', 'webhook_secret' => 'whsec_hidden']]);

    expect(DB::table('payment_accounts')->value('credentials'))->not->toContain('supersecret');

    $this->actingAs($user)
        ->get(route('payments.index', ['current_team' => $user->currentTeam->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/Index')
            ->where('providers.0.connected', true)
            ->where('providers.0.hint', '...alue'));

    expect($this->get(route('payments.index', ['current_team' => $user->currentTeam->slug]))->getContent())
        ->not->toContain('supersecretvalue')
        ->not->toContain('whsec_hidden');
});

test('invalid stripe keys are rejected and nothing is saved', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['error' => 'bad key'], 401)]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('payments.connect', ['current_team' => $user->currentTeam->slug, 'provider' => 'stripe']), ['secret_key' => 'sk_test_wrongkey'])
        ->assertSessionHasErrors('credentials');

    expect(PaymentAccount::count())->toBe(0);
});

test('a key that is not a stripe secret key fails validation without calling stripe', function () {
    Http::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('payments.connect', ['current_team' => $user->currentTeam->slug, 'provider' => 'stripe']), ['secret_key' => 'pk_live_publishable'])
        ->assertSessionHasErrors('secret_key');

    Http::assertNothingSent();
});

test('paypal is connected after the credentials get a token', function () {
    Http::fake(['api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token'])]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('payments.connect', ['current_team' => $user->currentTeam->slug, 'provider' => 'paypal']), [
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
            'mode' => 'sandbox',
        ])
        ->assertSessionHasNoErrors();

    expect($user->currentTeam->paymentAccounts()->sole()->provider)->toBe(PaymentProvider::PayPal);
});

test('reconnecting stripe keeps the saved webhook secret and key', function () {
    Http::fake(['api.stripe.com/v1/account' => Http::response(['email' => 'owner@example.com'])]);
    $user = User::factory()->create();
    $account = PaymentAccount::factory()->for($user->currentTeam)->create(['credentials' => ['secret_key' => 'sk_test_old', 'webhook_secret' => 'whsec_kept']]);

    $this->actingAs($user)
        ->put(route('payments.connect', ['current_team' => $user->currentTeam->slug, 'provider' => 'stripe']), ['secret_key' => 'sk_test_new'])
        ->assertSessionHasNoErrors();

    $fresh = $account->fresh();

    expect($user->currentTeam->paymentAccounts()->count())->toBe(1)
        ->and($fresh->credential('secret_key'))->toBe('sk_test_new')
        ->and($fresh->credential('webhook_secret'))->toBe('whsec_kept')
        ->and($fresh->webhook_key)->toBe($account->webhook_key);
});

test('a provider can be disconnected', function () {
    $user = User::factory()->create();
    PaymentAccount::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->delete(route('payments.disconnect', ['current_team' => $user->currentTeam->slug, 'provider' => 'stripe']))
        ->assertSessionHasNoErrors();

    expect(PaymentAccount::count())->toBe(0);
});

test('team members who are not admins cannot manage payment accounts', function () {
    Http::fake();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $owner->currentTeam->members()->attach($member, ['role' => 'member']);
    $member->switchTeam($owner->currentTeam);

    $this->actingAs($member)
        ->put(route('payments.connect', ['current_team' => $owner->currentTeam->slug, 'provider' => 'stripe']), ['secret_key' => 'sk_test_abc'])
        ->assertForbidden();

    expect(PaymentAccount::count())->toBe(0);
});

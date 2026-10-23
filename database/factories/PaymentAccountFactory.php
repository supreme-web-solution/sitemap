<?php

namespace Database\Factories;

use App\Enums\PaymentProvider;
use App\Models\PaymentAccount;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'provider' => PaymentProvider::Stripe,
            'credentials' => ['secret_key' => 'sk_test_'.Str::random(24), 'webhook_secret' => 'whsec_'.Str::random(24)],
            'label' => 'Test account',
            'webhook_key' => Str::random(32),
        ];
    }

    /**
     * Connect PayPal instead of Stripe.
     */
    public function paypal(): static
    {
        return $this->state(fn () => [
            'provider' => PaymentProvider::PayPal,
            'credentials' => ['client_id' => 'client-'.Str::random(20), 'client_secret' => Str::random(30), 'mode' => 'sandbox'],
        ]);
    }

    /**
     * Connect Paystack instead of Stripe.
     */
    public function paystack(): static
    {
        return $this->state(fn () => [
            'provider' => PaymentProvider::Paystack,
            'credentials' => ['secret_key' => 'sk_test_'.Str::random(24)],
        ]);
    }

    /**
     * Connect Flutterwave instead of Stripe.
     */
    public function flutterwave(): static
    {
        return $this->state(fn () => [
            'provider' => PaymentProvider::Flutterwave,
            'credentials' => ['secret_key' => 'FLWSECK_TEST-'.Str::random(16), 'webhook_hash' => 'hash_'.Str::random(16)],
        ]);
    }
}

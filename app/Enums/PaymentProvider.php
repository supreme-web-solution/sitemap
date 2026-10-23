<?php

namespace App\Enums;

use App\Services\Payments\FlutterwaveGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PayPalGateway;
use App\Services\Payments\PaystackGateway;
use App\Services\Payments\StripeGateway;

enum PaymentProvider: string
{
    case Stripe = 'stripe';
    case PayPal = 'paypal';
    case Paystack = 'paystack';
    case Flutterwave = 'flutterwave';

    /**
     * Get the display label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::PayPal => 'PayPal',
            self::Paystack => 'Paystack',
            self::Flutterwave => 'Flutterwave',
        };
    }

    /**
     * Get the service that talks to the provider.
     */
    public function gateway(): PaymentGateway
    {
        return match ($this) {
            self::Stripe => app(StripeGateway::class),
            self::PayPal => app(PayPalGateway::class),
            self::Paystack => app(PaystackGateway::class),
            self::Flutterwave => app(FlutterwaveGateway::class),
        };
    }

    /**
     * Get the label shown on the invoice's pay button.
     */
    public function payLabel(): string
    {
        return match ($this) {
            self::Stripe => 'Pay by card',
            self::PayPal => 'Pay with PayPal',
            self::Paystack => 'Pay with Paystack',
            self::Flutterwave => 'Pay with Flutterwave',
        };
    }

    /**
     * Named route that receives this provider's webhooks.
     */
    public function webhookRoute(): string
    {
        return match ($this) {
            self::Stripe => 'webhooks.stripe',
            self::PayPal => 'webhooks.paypal',
            self::Paystack => 'webhooks.paystack',
            self::Flutterwave => 'webhooks.flutterwave',
        };
    }

    /**
     * Credential key that holds the webhook signing secret, if any.
     */
    public function webhookSecretKey(): string
    {
        return match ($this) {
            self::Stripe, self::Paystack => 'webhook_secret',
            self::PayPal => 'webhook_id',
            self::Flutterwave => 'webhook_hash',
        };
    }
}

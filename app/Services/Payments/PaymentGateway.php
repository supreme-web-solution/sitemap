<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\PaymentAccount;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * Check the credentials against the provider and describe the connected account.
     *
     * @param  array<string, string>  $credentials
     * @return string A label for the account, e.g. the business name.
     *
     * @throws PaymentException
     */
    public function verifyCredentials(array $credentials): string;

    /**
     * Start a payment for the invoice and return the provider's page to send the customer to.
     *
     * @throws PaymentException
     */
    public function startPayment(Invoice $invoice, PaymentAccount $account, string $returnUrl, string $cancelUrl): string;

    /**
     * Finish a payment when the customer comes back, and report whether the invoice was paid.
     * Marks the invoice paid when it was.
     *
     * @throws PaymentException
     */
    public function completePayment(Invoice $invoice, PaymentAccount $account, Request $request): bool;

    /**
     * Return a captured payment to the customer.
     *
     * @throws PaymentException
     */
    public function refundPayment(Invoice $invoice, PaymentAccount $account): void;
}

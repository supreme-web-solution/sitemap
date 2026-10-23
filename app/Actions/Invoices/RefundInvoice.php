<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Enums\PaymentProvider;
use App\Models\Invoice;
use App\Services\Payments\PaymentException;

class RefundInvoice
{
    /**
     * Return the money when the provider still holds it, then mark the invoice refunded.
     *
     * @throws PaymentException
     */
    public function handle(Invoice $invoice, ?int $userId = null): void
    {
        if ($invoice->status !== InvoiceStatus::Paid) {
            throw new PaymentException(__('Only paid invoices can be refunded.'));
        }

        $provider = PaymentProvider::tryFrom((string) $invoice->paid_via);

        if ($provider !== null && filled($invoice->payment_reference)) {
            $account = $invoice->team->paymentAccounts()->where('provider', $provider)->first();

            if ($account === null) {
                throw new PaymentException(__('The original payment account is no longer connected.'));
            }

            $provider->gateway()->refundPayment($invoice, $account);
        }

        $invoice->forceFill([
            'status' => InvoiceStatus::Refunded,
            'refunded_at' => now(),
        ])->save();

        $invoice->lead->recordActivity(
            LeadActivityType::InvoicePaid,
            __('Invoice :number refunded (:amount)', ['number' => $invoice->number, 'amount' => Invoice::formatCents($invoice->total_cents)]),
            ['invoice_id' => $invoice->id],
            $userId,
        );
    }
}

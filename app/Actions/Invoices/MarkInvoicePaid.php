<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Models\Invoice;

class MarkInvoicePaid
{
    /**
     * Record that the invoice was paid. Safe to call more than once: only the first call counts.
     */
    public function handle(Invoice $invoice, string $via, ?string $reference = null, ?int $userId = null): bool
    {
        $updated = Invoice::query()
            ->whereKey($invoice->id)
            ->where('status', InvoiceStatus::Open)
            ->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
                'paid_via' => $via,
                'payment_reference' => $reference,
                'pending_reference' => null,
            ]);

        if ($updated === 0) {
            return false;
        }

        $invoice->refresh();
        $lead = $invoice->lead;

        $lead->recordActivity(
            LeadActivityType::InvoicePaid,
            __('Invoice :number paid (:amount via :via)', [
                'number' => $invoice->number,
                'amount' => Invoice::formatCents($invoice->total_cents),
                'via' => $via,
            ]),
            ['invoice_id' => $invoice->id, 'via' => $via, 'reference' => $reference],
            $userId,
        );

        if (! in_array($lead->status, [LeadStatus::Won, LeadStatus::Lost], true)) {
            $lead->update(['status' => LeadStatus::Won, 'next_follow_up_at' => null]);
        }

        return true;
    }
}

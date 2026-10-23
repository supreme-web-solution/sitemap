<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Mail\InvoiceEmail;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GenerateRecurringInvoice
{
    /**
     * Issue the schedule's invoice and move it on to its next date.
     *
     * Safe to run twice at once: only the run that claims the date issues an invoice.
     */
    public function handle(RecurringInvoice $schedule): ?Invoice
    {
        [$next, $cycles] = $schedule->nextRunAfter(today());

        $invoice = DB::transaction(function () use ($schedule, $next, $cycles) {
            $claimed = RecurringInvoice::query()
                ->whereKey($schedule->id)
                ->where('is_active', true)
                ->whereDate('next_run_on', $schedule->next_run_on)
                ->update([
                    'cycles_run' => $cycles,
                    'next_run_on' => $next?->toDateString(),
                    'is_active' => $next !== null,
                    'last_run_at' => now(),
                ]);

            if ($claimed === 0) {
                return null;
            }

            $invoice = $schedule->team->invoices()->make([
                'lead_id' => $schedule->lead_id,
                'number' => Invoice::nextNumber($schedule->team),
                'status' => InvoiceStatus::Open,
                'total_cents' => $schedule->totalCents(),
                'due_on' => today()->addDays($schedule->due_in_days),
                'notes' => $schedule->notes,
            ]);
            $invoice->forceFill(['recurring_invoice_id' => $schedule->id, 'sent_at' => now()])->save();
            $invoice->items()->createMany($schedule->items);

            $schedule->lead->recordActivity(
                LeadActivityType::InvoiceSent,
                __('Recurring invoice :number issued (:amount)', ['number' => $invoice->number, 'amount' => Invoice::formatCents($invoice->total_cents)]),
                ['invoice_id' => $invoice->id, 'recurring_invoice_id' => $schedule->id],
            );

            return $invoice;
        });

        if ($invoice !== null && $schedule->email_client && filled($schedule->lead->email)) {
            $owner = $schedule->team->owner();

            Mail::to($schedule->lead->email)->queue(new InvoiceEmail($invoice, $owner?->name ?? $schedule->team->name, $owner?->email));
            $invoice->forceFill(['emailed_at' => now()])->save();
        }

        return $invoice;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\LeadActivityType;
use App\Mail\InvoiceReminderEmail;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('invoices:send-reminders')]
#[Description('Email clients a reminder about invoices that are overdue')]
class SendInvoiceReminders extends Command
{
    /**
     * The most reminders sent for one invoice.
     */
    public const MAX_REMINDERS = 3;

    /**
     * Days to wait between reminders.
     */
    public const DAYS_BETWEEN_REMINDERS = 7;

    /**
     * Execute the console command.
     *
     * Only invoices that were emailed to the client get reminders, so nobody is chased for an invoice they were never sent by email.
     */
    public function handle(): int
    {
        $sent = 0;

        Invoice::query()
            ->outstanding()
            ->whereNotNull('emailed_at')
            ->where('reminders_sent', '<', self::MAX_REMINDERS)
            ->whereDate('due_on', '<', today())
            ->where(fn ($query) => $query->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subDays(self::DAYS_BETWEEN_REMINDERS)))
            ->with(['lead', 'team'])
            ->chunkById(100, function ($invoices) use (&$sent) {
                foreach ($invoices as $invoice) {
                    if (blank($invoice->lead->email) || ! $invoice->isPayable()) {
                        continue;
                    }

                    $owner = $invoice->team->owner();

                    Mail::to($invoice->lead->email)->queue(new InvoiceReminderEmail($invoice, $owner?->name ?? $invoice->team->name, $owner?->email));

                    $invoice->forceFill(['reminders_sent' => $invoice->reminders_sent + 1, 'last_reminded_at' => now()])->save();
                    $invoice->lead->recordActivity(
                        LeadActivityType::InvoiceReminder,
                        __('Reminder sent for overdue invoice :number', ['number' => $invoice->number]),
                        ['invoice_id' => $invoice->id, 'reminder' => $invoice->reminders_sent],
                    );

                    $sent++;
                }
            });

        $this->info("Sent {$sent} invoice reminder(s).");

        return self::SUCCESS;
    }
}

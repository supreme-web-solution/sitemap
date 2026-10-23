<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Mail\InvoiceEmail;
use App\Mail\InvoiceReminderEmail;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\RecurringInvoice;
use App\Models\Team;
use Illuminate\Support\Facades\Mail;

/**
 * @return array{0: Team, 1: Lead}
 */
function billingTeam(?string $email = 'client@example.com'): array
{
    $lead = Lead::factory()->create(['email' => $email]);

    return [$lead->team, $lead];
}

test('a due schedule issues an open invoice, emails the client and moves to the next month', function () {
    Mail::fake();
    [, $lead] = billingTeam();
    $schedule = RecurringInvoice::factory()->create(['lead_id' => $lead->id, 'due_in_days' => 10, 'notes' => 'Thank you']);

    $this->artisan('invoices:run-recurring')->assertSuccessful();

    $invoice = Invoice::sole();

    expect($invoice->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->total_cents)->toBe(5000)
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->due_on->toDateString())->toBe(today()->addDays(10)->toDateString())
        ->and($invoice->notes)->toBe('Thank you')
        ->and($invoice->recurring_invoice_id)->toBe($schedule->id)
        ->and($invoice->emailed_at)->not->toBeNull()
        ->and($invoice->number)->toBe('INV-0001')
        ->and($lead->activities()->where('type', LeadActivityType::InvoiceSent)->count())->toBe(1);

    Mail::assertQueued(InvoiceEmail::class, fn (InvoiceEmail $mail) => $mail->hasTo('client@example.com'));

    $fresh = $schedule->fresh();

    expect($fresh->next_run_on->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString())
        ->and($fresh->cycles_run)->toBe(1)
        ->and($fresh->is_active)->toBeTrue();
});

test('running the command twice on the same day issues one invoice', function () {
    Mail::fake();
    [, $lead] = billingTeam();
    RecurringInvoice::factory()->create(['lead_id' => $lead->id]);

    $this->artisan('invoices:run-recurring');
    $this->artisan('invoices:run-recurring');

    expect(Invoice::count())->toBe(1);
});

test('future, paused and email-less schedules behave', function () {
    Mail::fake();
    [, $lead] = billingTeam();
    [, $noEmailLead] = billingTeam(null);
    RecurringInvoice::factory()->create(['lead_id' => $lead->id, 'starts_on' => today()->addDay(), 'next_run_on' => today()->addDay()]);
    RecurringInvoice::factory()->paused()->create(['lead_id' => $lead->id]);
    RecurringInvoice::factory()->create(['lead_id' => $noEmailLead->id]);

    $this->artisan('invoices:run-recurring');

    $invoice = Invoice::sole();

    expect($invoice->lead_id)->toBe($noEmailLead->id)->and($invoice->emailed_at)->toBeNull();
    Mail::assertNothingQueued();
});

test('a schedule that is behind issues one invoice and skips to the next future date', function () {
    Mail::fake();
    [, $lead] = billingTeam();
    $schedule = RecurringInvoice::factory()->create([
        'lead_id' => $lead->id,
        'starts_on' => today()->subMonths(3)->subDays(2),
        'next_run_on' => today()->subMonths(2)->subDays(2),
        'cycles_run' => 1,
    ]);

    $this->artisan('invoices:run-recurring');

    expect(Invoice::count())->toBe(1)
        ->and($schedule->fresh()->next_run_on->isFuture())->toBeTrue()
        ->and($schedule->fresh()->cycles_run)->toBe(4);
});

test('a schedule ends after its end date', function () {
    Mail::fake();
    [, $lead] = billingTeam();
    $schedule = RecurringInvoice::factory()->create(['lead_id' => $lead->id, 'ends_on' => today()->addDays(5)]);

    $this->artisan('invoices:run-recurring');

    $fresh = $schedule->fresh();

    expect(Invoice::count())->toBe(1)
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->next_run_on)->toBeNull()
        ->and($fresh->hasEnded())->toBeTrue();

    $this->artisan('invoices:run-recurring');

    expect(Invoice::count())->toBe(1);
});

test('month end dates do not drift', function () {
    $schedule = RecurringInvoice::factory()->make(['starts_on' => '2027-01-31', 'cycles_run' => 0]);

    [$february, $cycles] = $schedule->nextRunAfter(Carbon\Carbon::parse('2027-01-31'));
    $schedule->cycles_run = $cycles;
    [$march] = $schedule->nextRunAfter(Carbon\Carbon::parse('2027-02-28'));

    expect($february->toDateString())->toBe('2027-02-28')->and($march->toDateString())->toBe('2027-03-31');
});

test('overdue emailed invoices get a reminder, then wait a week, and stop after three', function () {
    Mail::fake();
    [, $lead] = billingTeam();
    $invoice = Invoice::factory()->create(['lead_id' => $lead->id, 'due_on' => today()->subDays(2), 'emailed_at' => now()->subDays(10)]);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertQueued(InvoiceReminderEmail::class, 1);
    expect($invoice->fresh()->reminders_sent)->toBe(1)
        ->and($lead->activities()->where('type', LeadActivityType::InvoiceReminder)->count())->toBe(1);

    $this->artisan('invoices:send-reminders');
    Mail::assertQueued(InvoiceReminderEmail::class, 1);

    $this->travel(8)->days();
    $this->artisan('invoices:send-reminders');
    $this->travel(8)->days();
    $this->artisan('invoices:send-reminders');
    $this->travel(8)->days();
    $this->artisan('invoices:send-reminders');

    Mail::assertQueued(InvoiceReminderEmail::class, 3);
    expect($invoice->fresh()->reminders_sent)->toBe(3);
});

test('reminders skip invoices that are not due, paid, voided, not emailed or have no address', function () {
    Mail::fake();
    [, $lead] = billingTeam();
    [, $noEmailLead] = billingTeam(null);
    $overdue = ['due_on' => today()->subDays(3), 'emailed_at' => now()->subDays(5)];

    Invoice::factory()->create(['lead_id' => $lead->id, ...$overdue, 'due_on' => today()]);
    Invoice::factory()->create(['lead_id' => $lead->id, ...$overdue, 'status' => InvoiceStatus::Paid]);
    Invoice::factory()->create(['lead_id' => $lead->id, ...$overdue, 'status' => InvoiceStatus::Void]);
    Invoice::factory()->create(['lead_id' => $lead->id, ...$overdue, 'emailed_at' => null]);
    Invoice::factory()->create(['lead_id' => $noEmailLead->id, ...$overdue]);

    $this->artisan('invoices:send-reminders')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('both commands run on the daily schedule', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('invoices:run-recurring')
        ->expectsOutputToContain('invoices:send-reminders')
        ->assertSuccessful();
});

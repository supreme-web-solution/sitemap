<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\BillingInterval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\SaveRecurringInvoiceRequest;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\RecurringInvoice;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RecurringInvoiceController extends Controller
{
    /**
     * List the team's recurring billing schedules.
     */
    public function index(Team $currentTeam): Response
    {
        return Inertia::render('invoices/Recurring', [
            'schedules' => $currentTeam->recurringInvoices()
                ->with('lead:id,name,email')
                ->latest('id')
                ->get()
                ->map(fn (RecurringInvoice $schedule) => [
                    'id' => $schedule->id,
                    'leadId' => $schedule->lead_id,
                    'leadName' => $schedule->lead->name,
                    'leadEmail' => $schedule->lead->email,
                    'interval' => $schedule->interval->value,
                    'intervalLabel' => $schedule->interval->label(),
                    'startsOn' => $schedule->starts_on->toDateString(),
                    'nextRunOn' => $schedule->next_run_on?->toDateString(),
                    'endsOn' => $schedule->ends_on?->toDateString(),
                    'dueInDays' => $schedule->due_in_days,
                    'emailClient' => $schedule->email_client,
                    'notes' => $schedule->notes,
                    'status' => $schedule->hasEnded() ? 'ended' : ($schedule->is_active ? 'active' : 'paused'),
                    'items' => collect($schedule->items)->map(fn (array $item) => [
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unitPrice' => $item['unit_cents'] / 100,
                    ])->all(),
                    'total' => Invoice::formatCents($schedule->totalCents()),
                    'cyclesRun' => $schedule->cycles_run,
                    'lastRunAt' => $schedule->last_run_at?->toISOString(),
                ]),
            'leads' => $currentTeam->leads()->orderBy('name')->limit(1000)->get(['id', 'name', 'email'])
                ->map(fn (Lead $lead) => ['id' => $lead->id, 'name' => $lead->name, 'email' => $lead->email])
                ->all(),
            'intervals' => collect(BillingInterval::cases())->map(fn (BillingInterval $interval) => ['value' => $interval->value, 'label' => $interval->label()])->values(),
            'today' => today()->toDateString(),
        ]);
    }

    /**
     * Start billing a client on a schedule.
     */
    public function store(SaveRecurringInvoiceRequest $request, Team $currentTeam): RedirectResponse
    {
        $currentTeam->recurringInvoices()
            ->make([
                ...$request->safe()->only(['lead_id', 'interval', 'starts_on', 'ends_on', 'due_in_days', 'email_client', 'notes']),
                'items' => $request->lineItems(),
            ])
            ->forceFill(['next_run_on' => $request->validated('starts_on'), 'is_active' => true])
            ->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring billing scheduled. The first invoice is issued on the start date.')]);

        return back();
    }

    /**
     * Change what future invoices contain, or when the schedule stops.
     */
    public function update(SaveRecurringInvoiceRequest $request, Team $currentTeam, RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $recurringInvoice->update([
            ...$request->safe()->only(['ends_on', 'due_in_days', 'email_client', 'notes']),
            'items' => $request->lineItems(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule updated. Invoices already issued are not changed.')]);

        return back();
    }

    /**
     * Pause or resume a schedule. Resuming skips any dates missed while paused.
     */
    public function toggle(Team $currentTeam, RecurringInvoice $recurringInvoice): RedirectResponse
    {
        if ($recurringInvoice->is_active) {
            $recurringInvoice->forceFill(['is_active' => false])->save();
            $message = __('Schedule paused.');
        } else {
            if ($recurringInvoice->hasEnded()) {
                throw ValidationException::withMessages(['schedule' => __('This schedule has finished. Create a new one to keep billing.')]);
            }

            $next = $recurringInvoice->next_run_on;

            if ($next->lessThan(today())) {
                [$next, $cycles] = $recurringInvoice->nextRunAfter(today());
                $recurringInvoice->cycles_run = $cycles;
            }

            $recurringInvoice->forceFill(['is_active' => $next !== null, 'next_run_on' => $next])->save();
            $message = __('Schedule resumed.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Delete a schedule. Invoices it already issued are kept.
     */
    public function destroy(Team $currentTeam, RecurringInvoice $recurringInvoice): RedirectResponse
    {
        $recurringInvoice->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule deleted.')]);

        return back();
    }
}

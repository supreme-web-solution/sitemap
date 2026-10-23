<?php

namespace App\Actions\Proposals;

use App\Actions\Invoices\GenerateRecurringInvoice;
use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\ProposalStatus;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcceptProposal
{
    /**
     * Days a client has to pay the invoices created from an accepted proposal.
     */
    protected const DUE_IN_DAYS = 14;

    public function __construct(private GenerateRecurringInvoice $generateRecurringInvoice) {}

    /**
     * Accept the proposal: bill the one-time items now, start recurring billing for the rest,
     * and mark the lead as won.
     *
     * Returns the first invoice the client should pay, or null when the proposal was already closed.
     */
    public function handle(Proposal $proposal, string $acceptedBy): ?Invoice
    {
        $proposal->loadMissing(['items', 'team', 'lead']);

        $schedules = new Collection;
        $oneTimeInvoice = null;

        $accepted = DB::transaction(function () use ($proposal, $acceptedBy, &$schedules, &$oneTimeInvoice) {
            $claimed = Proposal::query()
                ->whereKey($proposal->id)
                ->where('status', ProposalStatus::Sent)
                ->update(['status' => ProposalStatus::Accepted, 'accepted_at' => now(), 'accepted_name' => $acceptedBy]);

            if ($claimed === 0) {
                return false;
            }

            $oneTime = $proposal->items->whereNull('interval');

            if ($oneTime->isNotEmpty()) {
                $oneTimeInvoice = $proposal->team->invoices()->make([
                    'lead_id' => $proposal->lead_id,
                    'number' => Invoice::nextNumber($proposal->team),
                    'status' => InvoiceStatus::Open,
                    'total_cents' => $proposal->oneTimeCents(),
                    'due_on' => today()->addDays(self::DUE_IN_DAYS),
                    'notes' => $proposal->title,
                ]);
                $oneTimeInvoice->forceFill(['sent_at' => now()])->save();
                $oneTimeInvoice->items()->createMany($oneTime->map(fn (ProposalItem $item) => $this->lineItem($item))->values()->all());

                $proposal->forceFill(['invoice_id' => $oneTimeInvoice->id])->save();
            }

            $proposal->items
                ->filter(fn (ProposalItem $item) => $item->interval !== null)
                ->groupBy(fn (ProposalItem $item) => $item->interval->value)
                ->each(function (Collection $items, string $interval) use ($proposal, &$schedules) {
                    $schedule = $proposal->team->recurringInvoices()->make([
                        'lead_id' => $proposal->lead_id,
                        'interval' => BillingInterval::from($interval),
                        'starts_on' => today(),
                        'due_in_days' => self::DUE_IN_DAYS,
                        'email_client' => true,
                        'notes' => $proposal->title,
                        'items' => $items->map(fn (ProposalItem $item) => $this->lineItem($item))->values()->all(),
                    ]);
                    $schedule->forceFill(['next_run_on' => today(), 'is_active' => true])->save();

                    $schedules->push($schedule);
                });

            $proposal->lead->recordActivity(
                LeadActivityType::ProposalAccepted,
                __('Proposal :number accepted by :name (:amount first-year value)', ['number' => $proposal->number, 'name' => $acceptedBy, 'amount' => Invoice::formatCents($proposal->firstYearCents())]),
                ['proposal_id' => $proposal->id],
            );

            $proposal->lead->update([
                'status' => LeadStatus::Won,
                'deal_value' => $proposal->lead->deal_value ?? intdiv($proposal->firstYearCents(), 100),
            ]);

            return true;
        });

        if (! $accepted) {
            return null;
        }

        $firstRecurring = $schedules
            ->map(fn ($schedule) => $this->generateRecurringInvoice->handle($schedule))
            ->filter()
            ->first();

        $proposal->refresh();

        return $oneTimeInvoice ?? $firstRecurring;
    }

    /**
     * @return array{description: string, quantity: int, unit_cents: int}
     */
    protected function lineItem(ProposalItem $item): array
    {
        return [
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_cents' => $item->unit_cents,
        ];
    }
}
<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PaymentProvider;
use App\Enums\RecurringCollectionMode;
use Carbon\CarbonInterface;
use Database\Factories\RecurringInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $lead_id
 * @property BillingInterval $interval
 * @property Carbon $starts_on
 * @property Carbon|null $next_run_on
 * @property Carbon|null $ends_on
 * @property int $due_in_days
 * @property bool $email_client
 * @property RecurringCollectionMode $collection_mode
 * @property PaymentProvider|null $payment_provider
 * @property bool $is_active
 * @property string|null $notes
 * @property list<array{description: string, quantity: int, unit_cents: int}> $items
 * @property int $cycles_run
 * @property Carbon|null $last_run_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Lead $lead
 */
#[Fillable(['lead_id', 'interval', 'starts_on', 'ends_on', 'due_in_days', 'email_client', 'collection_mode', 'payment_provider', 'notes', 'items'])]
class RecurringInvoice extends Model
{
    /** @use HasFactory<RecurringInvoiceFactory> */
    use HasFactory;

    /**
     * Limit the query to active schedules whose next invoice date has arrived.
     *
     * @param  Builder<RecurringInvoice>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->where('is_active', true)->whereNotNull('next_run_on')->whereDate('next_run_on', '<=', today());
    }

    /**
     * What each invoice from this schedule adds up to, in cents.
     */
    public function totalCents(): int
    {
        return (int) collect($this->items)->sum(fn (array $item) => $item['quantity'] * $item['unit_cents']);
    }

    /**
     * Work out the first invoice date after today, so a schedule that missed cycles skips ahead
     * rather than issuing a pile of back-dated invoices.
     *
     * @return array{0: Carbon|null, 1: int} The next date (null when the end date has passed) and the cycles counted so far.
     */
    public function nextRunAfter(CarbonInterface $today): array
    {
        $cycles = $this->cycles_run;

        do {
            $cycles++;
            $next = $this->starts_on->copy()->addMonthsNoOverflow($this->interval->months() * $cycles);
        } while ($next->lessThanOrEqualTo($today));

        if ($this->ends_on !== null && $next->greaterThan($this->ends_on)) {
            return [null, $cycles];
        }

        return [$next, $cycles];
    }

    /**
     * Whether the schedule has finished and will not issue any more invoices.
     */
    public function hasEnded(): bool
    {
        return ! $this->is_active && $this->next_run_on === null;
    }

    /**
     * Whether future cycles should charge a card saved on the lead.
     */
    public function autoCharges(): bool
    {
        return $this->collection_mode === RecurringCollectionMode::AutoCharge
            && in_array($this->payment_provider, [PaymentProvider::Stripe, PaymentProvider::PayPal], true);
    }

    /**
     * Get the team that bills the client.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the lead being billed.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the invoices this schedule has issued.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'starts_on' => 'date',
            'next_run_on' => 'date',
            'ends_on' => 'date',
            'due_in_days' => 'integer',
            'email_client' => 'boolean',
            'collection_mode' => RecurringCollectionMode::class,
            'payment_provider' => PaymentProvider::class,
            'is_active' => 'boolean',
            'items' => 'array',
            'cycles_run' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }
}

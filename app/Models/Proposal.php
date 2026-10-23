<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\ProposalStatus;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int $lead_id
 * @property string $number
 * @property string $public_token
 * @property string $title
 * @property string|null $intro
 * @property string|null $terms
 * @property ProposalStatus $status
 * @property Carbon|null $valid_until
 * @property Carbon|null $sent_at
 * @property Carbon|null $viewed_at
 * @property Carbon|null $accepted_at
 * @property string|null $accepted_name
 * @property Carbon|null $declined_at
 * @property int|null $invoice_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Lead $lead
 * @property-read Invoice|null $invoice
 * @property-read Collection<int, ProposalItem> $items
 */
#[Fillable(['lead_id', 'title', 'intro', 'terms', 'valid_until'])]
class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Proposal $proposal) {
            $proposal->public_token ??= Str::random(40);
        });
    }

    /**
     * Work out the next proposal number for the team, e.g. PROP-0004.
     */
    public static function nextNumber(Team $team): string
    {
        $last = $team->proposals()->latest('id')->value('number');
        $sequence = $last ? (int) preg_replace('/\D/', '', $last) : 0;

        return 'PROP-'.str_pad((string) ($sequence + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * What the client pays once, in cents.
     */
    public function oneTimeCents(): int
    {
        return (int) $this->items->whereNull('interval')->sum(fn (ProposalItem $item) => $item->totalCents());
    }

    /**
     * What the client pays each month, quarter or year: totals per billing interval.
     *
     * @return Collection<string, int>
     */
    public function recurringCents(): Collection
    {
        return $this->items
            ->filter(fn (ProposalItem $item) => $item->interval !== null)
            ->groupBy(fn (ProposalItem $item) => $item->interval->value)
            ->map(fn (Collection $items) => (int) $items->sum(fn (ProposalItem $item) => $item->totalCents()));
    }

    /**
     * The first-year value, used as the deal value when the proposal is accepted.
     */
    public function firstYearCents(): int
    {
        return $this->oneTimeCents() + (int) $this->recurringCents()->sum(
            fn (int $cents, string $interval) => $cents * intdiv(12, BillingInterval::from($interval)->months()),
        );
    }

    /**
     * Whether the validity date has passed.
     */
    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    /**
     * Whether the client can still accept or decline it.
     */
    public function isOpen(): bool
    {
        return $this->status === ProposalStatus::Sent && ! $this->isExpired();
    }

    /**
     * The public page where the client reads and accepts the proposal.
     */
    public function publicUrl(): string
    {
        return route('proposals.public', $this->public_token);
    }

    /**
     * Get the team that wrote the proposal.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the lead the proposal is for.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the invoice created when the proposal was accepted.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the proposal's line items.
     *
     * @return HasMany<ProposalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }
}

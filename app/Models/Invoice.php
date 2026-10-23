<?php

namespace App\Models;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int $lead_id
 * @property string $number
 * @property InvoiceStatus $status
 * @property string $currency
 * @property int $total_cents
 * @property Carbon|null $due_on
 * @property string|null $notes
 * @property string $public_token
 * @property Carbon|null $sent_at
 * @property Carbon|null $emailed_at
 * @property int $reminders_sent
 * @property Carbon|null $last_reminded_at
 * @property int|null $recurring_invoice_id
 * @property Carbon|null $paid_at
 * @property string|null $paid_via
 * @property string|null $payment_reference
 * @property string|null $pending_reference
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Lead $lead
 */
#[Fillable(['lead_id', 'number', 'status', 'kind', 'total_cents', 'due_on', 'notes'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * The smallest amount card processors will charge, in cents.
     */
    public const MINIMUM_CENTS = 50;

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->public_token ??= Str::random(40);
        });
    }

    /**
     * Format an amount of cents as dollars, e.g. $1,234.50.
     */
    public static function formatCents(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }

    /**
     * Work out the next invoice number for the team, e.g. INV-0007.
     */
    public static function nextNumber(Team $team): string
    {
        $last = $team->invoices()->latest('id')->value('number');
        $sequence = $last ? (int) preg_replace('/\D/', '', $last) : 0;

        return 'INV-'.str_pad((string) ($sequence + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Limit the query to invoices still waiting for payment.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Open);
    }

    /**
     * Add up the line items and store the total.
     */
    public function recalculateTotal(): void
    {
        $this->total_cents = (int) $this->items()->get()->sum(fn (InvoiceItem $item) => $item->totalCents());
    }

    /**
     * Whether a customer can pay the invoice right now.
     */
    public function isPayable(): bool
    {
        return $this->status === InvoiceStatus::Open && $this->total_cents >= self::MINIMUM_CENTS;
    }

    /**
     * Whether the due date has passed without payment.
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Open && $this->due_on !== null && $this->due_on->isPast() && ! $this->due_on->isToday();
    }

    /**
     * The public page where the customer views and pays the invoice.
     */
    public function payUrl(): string
    {
        return route('invoices.public', $this->public_token);
    }

    /**
     * Get the team that issued the invoice.
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
     * Get the invoice's line items.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'kind' => InvoiceKind::class,
            'total_cents' => 'integer',
            'due_on' => 'date',
            'sent_at' => 'datetime',
            'emailed_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'reminders_sent' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}

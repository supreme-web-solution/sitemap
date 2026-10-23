<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $proposal_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_cents
 * @property BillingInterval|null $interval
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Proposal $proposal
 */
#[Fillable(['description', 'quantity', 'unit_cents', 'interval'])]
class ProposalItem extends Model
{
    /**
     * The line total in cents.
     */
    public function totalCents(): int
    {
        return $this->quantity * $this->unit_cents;
    }

    /**
     * Get the proposal the item belongs to.
     *
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cents' => 'integer',
            'interval' => BillingInterval::class,
        ];
    }
}

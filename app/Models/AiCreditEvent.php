<?php

namespace App\Models;

use Database\Factories\AiCreditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A provider credit or quota failure recorded when AI site work finishes anyway.
 *
 * @property int $id
 * @property int|null $team_id
 * @property int|null $lead_id
 * @property string $provider
 * @property string $source
 * @property string $message
 * @property string|null $outcome
 * @property Carbon $occurred_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lead|null $lead
 * @property-read Team|null $team
 */
#[Fillable(['team_id', 'lead_id', 'provider', 'source', 'message', 'outcome', 'occurred_at', 'finished_at'])]
class AiCreditEvent extends Model
{
    /** @use HasFactory<AiCreditEventFactory> */
    use HasFactory;

    /**
     * The lead whose website was being written.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The team that owns the lead.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Provider name shown in the admin log.
     */
    public function providerLabel(): string
    {
        return match ($this->provider) {
            'anthropic' => 'Anthropic',
            'openai' => 'OpenAI',
            'openrouter' => 'OpenRouter',
            default => ucfirst($this->provider),
        };
    }

    /**
     * Where the failed call happened.
     */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            'site_creation' => 'Site creation',
            'site_editor' => 'Site editor',
            default => ucfirst(str_replace('_', ' ', $this->source)),
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

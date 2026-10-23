<?php

namespace App\Models;

use App\Enums\LeadSearchStatus;
use App\Enums\WebsiteFilter;
use Database\Factories\LeadSearchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string|null $keyword
 * @property string|null $location
 * @property string|null $place_url
 * @property WebsiteFilter $website_filter
 * @property float|null $minimum_rating
 * @property int $max_results
 * @property LeadSearchStatus $status
 * @property string|null $provider_job_id
 * @property int $results_count
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $user
 * @property-read Collection<int, Lead> $leads
 */
#[Fillable([
    'user_id',
    'keyword',
    'location',
    'place_url',
    'website_filter',
    'minimum_rating',
    'max_results',
    'status',
    'provider_job_id',
    'results_count',
    'error',
    'completed_at',
])]
class LeadSearch extends Model
{
    /** @use HasFactory<LeadSearchFactory> */
    use HasFactory;

    /**
     * Get the team that owns the search.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who ran the search.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the leads found by the search.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Mark the search as failed with the given reason.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => LeadSearchStatus::Failed,
            'error' => $error,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'website_filter' => WebsiteFilter::class,
            'minimum_rating' => 'float',
            'status' => LeadSearchStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}

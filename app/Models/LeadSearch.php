<?php

namespace App\Models;

use App\Enums\LeadSearchStatus;
use App\Enums\WebsiteFilter;
use App\Services\CommandCenter\CommandCenterNotifier;
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
 * @property bool $notify_command_center
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
    'notify_command_center',
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
            'error' => self::publicFacingError($error),
            'completed_at' => now(),
        ]);

        app(CommandCenterNotifier::class)->leadSearchFinished($this);
    }

    /**
     * Error text safe to show in the product UI (provider infra wording is stripped).
     */
    public static function publicFacingError(?string $error): ?string
    {
        if ($error === null || trim($error) === '') {
            return null;
        }

        $normalized = strtolower($error);

        $allowed = [
            'your mindcase wallet balance is too low to run this search.',
            'the lead finder api key is missing or invalid.',
            'the lead finder rejected this search. try a more specific location.',
            'the search timed out. please try again.',
            'the search could not be completed.',
            'the search could not be completed. please try again.',
        ];

        if (in_array($normalized, $allowed, true)) {
            return $error;
        }

        return 'The search could not be completed. Please try again.';
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
            'notify_command_center' => 'boolean',
            'status' => LeadSearchStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}

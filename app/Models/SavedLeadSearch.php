<?php

namespace App\Models;

use App\Enums\WebsiteFilter;
use Database\Factories\SavedLeadSearchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $name
 * @property string $keyword
 * @property string $location
 * @property WebsiteFilter $website_filter
 * @property float|null $minimum_rating
 * @property int $max_results
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'team_id',
    'user_id',
    'name',
    'keyword',
    'location',
    'website_filter',
    'minimum_rating',
    'max_results',
])]
class SavedLeadSearch extends Model
{
    /** @use HasFactory<SavedLeadSearchFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Criteria used to start a fresh LeadSearch run.
     *
     * @return array{keyword: string, location: string, website_filter: string, minimum_rating: float|null, max_results: int}
     */
    public function criteria(): array
    {
        return [
            'keyword' => $this->keyword,
            'location' => $this->location,
            'website_filter' => $this->website_filter->value,
            'minimum_rating' => $this->minimum_rating,
            'max_results' => $this->max_results,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'website_filter' => WebsiteFilter::class,
            'minimum_rating' => 'float',
            'max_results' => 'integer',
        ];
    }
}

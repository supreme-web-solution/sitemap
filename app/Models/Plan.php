<?php

namespace App\Models;

use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_cents
 * @property bool $is_default
 * @property bool $is_active
 * @property array<string, int|null> $limits
 * @property list<string> $features
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'price_cents', 'is_default', 'is_active', 'limits', 'features', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Get the plan teams are on when an administrator has not chosen one.
     */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Get the cap for a metric, or null when it is unlimited.
     */
    public function limit(UsageMetric $metric): ?int
    {
        $limit = $this->limits[$metric->value] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    /**
     * Whether the plan includes a feature.
     */
    public function allows(PlanFeature $feature): bool
    {
        return in_array($feature->value, $this->features, true);
    }

    /**
     * Get the teams on this plan.
     *
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'limits' => 'array',
            'features' => 'array',
            'sort_order' => 'integer',
        ];
    }
}

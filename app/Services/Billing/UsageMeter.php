<?php

namespace App\Services\Billing;

use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use App\Exceptions\PlanLimitException;
use App\Models\SiteDomain;
use App\Models\Team;
use App\Models\UsageRecord;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UsageMeter
{
    /**
     * Get the team's cap for a metric, or null when it is unlimited.
     *
     * A team with no plan (and no default plan set up) has no caps.
     */
    public function limit(Team $team, UsageMetric $metric): ?int
    {
        return $team->effectivePlan()?->limit($metric);
    }

    /**
     * How much of the metric the team has used: this month for monthly allowances, in total for the rest.
     */
    public function used(Team $team, UsageMetric $metric): int
    {
        if ($metric->isMonthly()) {
            return (int) UsageRecord::query()
                ->where('team_id', $team->id)
                ->where('metric', $metric->value)
                ->where('period', now()->format('Y-m'))
                ->value('count');
        }

        return match ($metric) {
            UsageMetric::Leads => $team->leads()->count(),
            UsageMetric::Sites => $team->sites()->count(),
            UsageMetric::Domains => SiteDomain::query()->whereIn('site_id', $team->sites()->select('sites.id'))->count(),
            UsageMetric::Members => $team->members()->count(),
            default => 0,
        };
    }

    /**
     * How much is left, or null when unlimited.
     */
    public function remaining(Team $team, UsageMetric $metric): ?int
    {
        $limit = $this->limit($team, $metric);

        return $limit === null ? null : max(0, $limit - $this->used($team, $metric));
    }

    /**
     * Stop the action when it would take the team past its cap.
     *
     * @throws PlanLimitException
     */
    public function ensureWithin(Team $team, UsageMetric $metric, int $amount = 1): void
    {
        $limit = $this->limit($team, $metric);

        if ($limit !== null && $this->used($team, $metric) + $amount > $limit) {
            throw PlanLimitException::exceeded($metric, $limit);
        }
    }

    /**
     * Count monthly usage. Totals are counted from the data itself, so they need no recording.
     */
    public function record(Team $team, UsageMetric $metric, int $amount = 1): void
    {
        if (! $metric->isMonthly()) {
            return;
        }

        UsageRecord::query()
            ->firstOrCreate(['team_id' => $team->id, 'metric' => $metric->value, 'period' => now()->format('Y-m')], ['count' => 0])
            ->increment('count', $amount);
    }

    /**
     * Check the cap and count the usage in one step.
     *
     * @throws PlanLimitException
     */
    public function consume(Team $team, UsageMetric $metric, int $amount = 1): void
    {
        if (! $metric->isMonthly()) {
            $this->ensureWithin($team, $metric, $amount);

            return;
        }

        DB::transaction(function () use ($team, $metric, $amount) {
            $period = now()->format('Y-m');

            $record = UsageRecord::query()
                ->where('team_id', $team->id)
                ->where('metric', $metric->value)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $used = (int) ($record?->count ?? 0);
            $limit = $this->limit($team, $metric);

            if ($limit !== null && $used + $amount > $limit) {
                throw PlanLimitException::exceeded($metric, $limit);
            }

            if ($record === null) {
                try {
                    UsageRecord::query()->create([
                        'team_id' => $team->id,
                        'metric' => $metric->value,
                        'period' => $period,
                        'count' => $amount,
                    ]);

                    return;
                } catch (UniqueConstraintViolationException) {
                    $record = UsageRecord::query()
                        ->where('team_id', $team->id)
                        ->where('metric', $metric->value)
                        ->where('period', $period)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $record->increment('count', $amount);
        });
    }

    /**
     * Whether the team's plan includes the feature. Teams with no plan get everything.
     */
    public function allows(Team $team, PlanFeature $feature): bool
    {
        $plan = $team->effectivePlan();

        return $plan === null || $plan->allows($feature);
    }

    /**
     * @throws PlanLimitException
     */
    public function ensureFeature(Team $team, PlanFeature $feature): void
    {
        if (! $this->allows($team, $feature)) {
            throw PlanLimitException::featureLocked($feature);
        }
    }

    /**
     * Every metric with its usage and cap, for display.
     *
     * @return list<array{key: string, label: string, period: string, used: int, limit: int|null}>
     */
    public function snapshot(Team $team): array
    {
        return collect(UsageMetric::cases())->map(fn (UsageMetric $metric) => [
            'key' => $metric->value,
            'label' => $metric->label(),
            'period' => $metric->isMonthly() ? 'month' : 'total',
            'used' => $this->used($team, $metric),
            'limit' => $this->limit($team, $metric),
        ])->all();
    }
}

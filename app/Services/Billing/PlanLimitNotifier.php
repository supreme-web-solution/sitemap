<?php

namespace App\Services\Billing;

use App\Enums\UsageMetric;
use App\Models\Team;
use App\Models\User;
use App\Notifications\PlanLimitReachedNotification;
use Illuminate\Support\Facades\Cache;

class PlanLimitNotifier
{
    /**
     * Hours before the same team + metric can email again.
     */
    public const THROTTLE_HOURS = 24;

    /**
     * Email the team when a plan cap blocks work, at most once per day per metric.
     */
    public function notify(Team $team, UsageMetric $metric, int $limit, ?int $blockedCount = null, ?string $context = null): void
    {
        if (! $this->shouldSend($team, $metric)) {
            return;
        }

        $recipient = $this->recipient($team);

        if ($recipient === null) {
            return;
        }

        $recipient->notify(new PlanLimitReachedNotification(
            team: $team,
            metric: $metric,
            limit: $limit,
            used: app(UsageMeter::class)->used($team, $metric),
            blockedCount: $blockedCount,
            context: $context,
        ));
    }

    /**
     * Only send once per throttle window for this team and metric.
     */
    protected function shouldSend(Team $team, UsageMetric $metric): bool
    {
        return Cache::add(
            $this->cacheKey($team, $metric),
            true,
            now()->addHours(self::THROTTLE_HOURS),
        );
    }

    protected function cacheKey(Team $team, UsageMetric $metric): string
    {
        return 'plan-limit-mail:'.$team->id.':'.$metric->value;
    }

    protected function recipient(Team $team): ?User
    {
        $owner = $team->owner();

        return $owner instanceof User ? $owner : null;
    }
}

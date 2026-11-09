<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Step-by-step progress for an in-flight website build.
 *
 * Every step is written to storage/logs/laravel.log and cached so the leads
 * table and the review page can show how far the build has got.
 */
class SiteBuildProgress
{
    /**
     * Seconds without a new step before the build is reported as slow.
     */
    public const STALLED_AFTER_SECONDS = 150;

    /**
     * Ordered build steps with the share of the bar each one reaches.
     *
     * @var array<string, array{label: string, percent: int}>
     */
    public const STEPS = [
        'queued' => ['label' => 'Waiting for a worker', 'percent' => 8],
        'reviews' => ['label' => 'Collecting Google reviews', 'percent' => 20],
        'started' => ['label' => 'Build started', 'percent' => 30],
        'writing' => ['label' => 'Writing the website copy', 'percent' => 60],
        'saving' => ['label' => 'Saving pages and layout', 'percent' => 82],
        'assets' => ['label' => 'Preparing logo and brand assets', 'percent' => 94],
    ];

    public static function key(int $leadId): string
    {
        return 'site-build-progress:'.$leadId;
    }

    /**
     * Record that a build step has been reached.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(int $leadId, string $step, array $context = []): void
    {
        if (! isset(self::STEPS[$step])) {
            return;
        }

        $state = self::state($leadId);

        $beginsNewBuild = $step === 'reviews' || ($step === 'queued' && ($state['step'] ?? null) !== 'reviews');

        if ($state === null || $beginsNewBuild) {
            $state = ['started_at' => now()->timestamp, 'steps' => []];
        }

        $order = array_keys(self::STEPS);
        $movesBackwards = isset($state['step']) && array_search($step, $order, true) < array_search($state['step'], $order, true);

        if (! $movesBackwards) {
            $state['step'] = $step;
        }

        $state['updated_at'] = now()->timestamp;
        $state['steps'][$step] ??= now()->timestamp;

        Cache::put(self::key($leadId), $state, now()->addMinutes(30));

        self::log($leadId, self::STEPS[$step]['label'], ['step' => $step, ...$context]);
    }

    /**
     * Log that the build finished and drop the progress state.
     */
    public static function finished(int $leadId): void
    {
        $state = self::state($leadId);

        self::log($leadId, 'Website build finished', [
            'step' => 'done',
            'seconds' => $state ? now()->timestamp - $state['started_at'] : null,
        ]);

        Cache::forget(self::key($leadId));
    }

    /**
     * Log that the build failed and drop the progress state.
     */
    public static function failed(int $leadId, ?string $reason = null): void
    {
        $state = self::state($leadId);

        self::log($leadId, 'Website build failed', [
            'step' => $state['step'] ?? null,
            'reason' => $reason,
        ], 'warning');

        Cache::forget(self::key($leadId));
    }

    /**
     * The progress payload shown in the UI for a queued build.
     *
     * @return array{percent: int, step: string, label: string, stalled: bool, startedAt: string|null, updatedAt: string|null, steps: list<array{key: string, label: string, percent: int, state: string}>}
     */
    public static function snapshot(int $leadId): array
    {
        $state = self::state($leadId);
        $current = $state['step'] ?? 'queued';
        $reached = $state['steps'] ?? [];

        $steps = [];
        $currentIndex = array_search($current, array_keys(self::STEPS), true);

        foreach (array_keys(self::STEPS) as $index => $key) {
            if ($key === 'reviews' && ! isset($reached['reviews'])) {
                continue;
            }

            $steps[] = [
                'key' => $key,
                'label' => __(self::STEPS[$key]['label']),
                'percent' => self::STEPS[$key]['percent'],
                'state' => match (true) {
                    $key === $current => 'active',
                    $index < $currentIndex => 'done',
                    default => 'pending',
                },
            ];
        }

        $updatedAt = $state['updated_at'] ?? null;

        return [
            'percent' => self::STEPS[$current]['percent'],
            'step' => $current,
            'label' => __(self::STEPS[$current]['label']),
            'stalled' => $updatedAt !== null && now()->timestamp - $updatedAt > self::STALLED_AFTER_SECONDS,
            'startedAt' => isset($state['started_at']) ? now()->setTimestamp($state['started_at'])->toIso8601String() : null,
            'updatedAt' => $updatedAt !== null ? now()->setTimestamp($updatedAt)->toIso8601String() : null,
            'steps' => $steps,
        ];
    }

    /**
     * @return array{started_at: int, updated_at: int, step: string, steps: array<string, int>}|null
     */
    protected static function state(int $leadId): ?array
    {
        $state = Cache::get(self::key($leadId));

        return is_array($state) ? $state : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function log(int $leadId, string $message, array $context, string $level = 'info'): void
    {
        Log::channel('site-edit')->{$level}('[site-build] '.$message, ['lead_id' => $leadId, ...$context]);
    }
}

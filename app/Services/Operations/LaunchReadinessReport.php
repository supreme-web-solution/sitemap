<?php

namespace App\Services\Operations;

/**
 * Aggregated launch-readiness results.
 */
readonly class LaunchReadinessReport
{
    /**
     * @param  list<LaunchCheck>  $checks
     */
    public function __construct(public array $checks) {}

    public function ok(): bool
    {
        return ! collect($this->checks)->contains(fn (LaunchCheck $check) => $check->status === 'fail');
    }

    /**
     * @return array{pass: int, warn: int, fail: int}
     */
    public function summary(): array
    {
        $counts = ['pass' => 0, 'warn' => 0, 'fail' => 0];

        foreach ($this->checks as $check) {
            if (isset($counts[$check->status])) {
                $counts[$check->status]++;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(),
            'summary' => $this->summary(),
            'checks' => array_map(fn (LaunchCheck $check) => $check->toArray(), $this->checks),
        ];
    }
}

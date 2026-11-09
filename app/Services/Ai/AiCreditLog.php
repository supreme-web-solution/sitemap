<?php

namespace App\Services\Ai;

use App\Models\AiCreditEvent;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Support\Str;

/**
 * Stores credit and quota failures so admins can see which provider failed and when the job finished.
 */
class AiCreditLog
{
    /**
     * Record the moment a provider refused the call for credits or quota.
     */
    public function record(?Lead $lead, string $providerLabel, string $source, string $message, ?Team $team = null): AiCreditEvent
    {
        return AiCreditEvent::query()->create([
            'team_id' => $lead?->team_id ?? $team?->id,
            'lead_id' => $lead?->id,
            'provider' => $this->providerKey($message, $providerLabel),
            'source' => $source,
            'message' => Str::limit($message, 1000),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Mark the same failure once generation or editing has finished.
     */
    public function finish(AiCreditEvent $event, string $outcome): AiCreditEvent
    {
        $event->forceFill([
            'outcome' => $outcome,
            'finished_at' => now(),
        ])->save();

        return $event;
    }

    /**
     * Prefer the provider named in the API error, then the planner label.
     */
    public function providerKey(string $message, string $providerLabel): string
    {
        if (preg_match('/provider \[([a-z0-9._-]+)\]/i', $message, $matches) === 1) {
            return strtolower($matches[1]);
        }

        $label = strtolower($providerLabel);

        foreach (['anthropic', 'openai', 'openrouter', 'mindcase'] as $name) {
            if (str_contains($label, $name) || str_contains(strtolower($message), $name)) {
                return $name;
            }
        }

        return 'unknown';
    }
}

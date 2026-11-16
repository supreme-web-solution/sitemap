<?php

namespace App\Services\Ai;

use App\Models\AiCreditEvent;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Writes OpenAI and OpenRouter photo credit failures onto the admin credit log.
 */
class ImageGenerationCreditLog
{
    public function __construct(protected AiCreditLog $credits) {}

    public function recordHttpFailure(Response $response, string $provider, int $teamId, int $leadId, string $source): void
    {
        if (! $this->isCreditFailure($response)) {
            return;
        }

        $message = trim((string) ($response->json('error.message') ?? ''));

        if ($message === '') {
            $message = 'HTTP '.$response->status();
        }

        $message = Str::limit($message, 1000);
        $lead = Lead::query()->find($leadId);
        $team = $lead->team ?? Team::query()->find($teamId);

        if ($this->alreadyRecorded($provider, $source, $message, $leadId, $team?->id)) {
            return;
        }

        $event = $this->credits->record($lead, $provider, $source, $message, $team);
        $this->credits->finish($event, 'Photo was not generated');
    }

    protected function isCreditFailure(Response $response): bool
    {
        if ($response->status() === 429) {
            return true;
        }

        $probe = strtolower(implode(' ', array_filter([
            (string) ($response->json('error.message') ?? ''),
            (string) ($response->json('error.type') ?? ''),
            (string) ($response->json('error.code') ?? ''),
        ])));

        return AiProviderErrors::isQuotaOrAuth($probe);
    }

    protected function alreadyRecorded(string $provider, string $source, string $message, int $leadId, ?int $teamId): bool
    {
        return AiCreditEvent::query()
            ->where('provider', $this->credits->providerKey($message, $provider))
            ->where('source', $source)
            ->where('message', $message)
            ->where('occurred_at', '>=', now()->subMinutes(10))
            ->when(
                $leadId > 0,
                fn ($query) => $query->where('lead_id', $leadId),
                fn ($query) => $query->where('team_id', $teamId),
            )
            ->exists();
    }
}

<?php

namespace App\Jobs;

use App\Ai\Agents\NicheBriefDesigner;
use App\Models\NicheBrief;
use App\Services\Ai\AiAvailability;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateNicheBrief implements ShouldQueue
{
    use Queueable;

    public int $timeout = 40;

    public int $tries = 1;

    public function __construct(public NicheBrief $brief)
    {
        $this->onQueue(Queues::AI);
    }

    /**
     * Fill a practical research brief (Laravel AI when configured, otherwise rule-based).
     */
    public function handle(): void
    {
        $industry = $this->brief->industry;
        $location = $this->brief->location;

        if (AiAvailability::anthropicConfigured()) {
            try {
                $response = (new NicheBriefDesigner)->prompt(
                    "Industry: {$industry}\nLocation: {$location}\n\nDraft a prospecting brief for selling websites to these businesses.",
                );

                $this->brief->forceFill([
                    'status' => 'done',
                    'error' => null,
                    'brief' => [
                        'summary' => (string) ($response['summary'] ?? ''),
                        'searchQueries' => array_values($response['searchQueries'] ?? []),
                        'idealCustomer' => (string) ($response['idealCustomer'] ?? ''),
                        'objections' => array_values($response['objections'] ?? []),
                        'openingLine' => (string) ($response['openingLine'] ?? ''),
                    ],
                ])->save();

                return;
            } catch (Throwable $exception) {
                Log::warning('Niche brief AI generation failed; using template brief.', [
                    'brief_id' => $this->brief->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->brief->forceFill([
            'status' => 'done',
            'error' => null,
            'brief' => $this->templateBrief($industry, $location),
        ])->save();
    }

    /**
     * @return array{summary: string, searchQueries: list<string>, idealCustomer: string, objections: list<string>, openingLine: string}
     */
    protected function templateBrief(string $industry, string $location): array
    {
        return [
            'summary' => "Local {$industry} businesses in {$location} that still rely on a weak website or none at all are the easiest first conversations.",
            'searchQueries' => [
                "{$industry} in {$location}",
                "{$industry} near {$location} with no website",
                "best {$industry} {$location}",
            ],
            'idealCustomer' => "Owner-operated {$industry} shops in {$location} with a phone number, a Google rating of 4+, and either no website or a site that is not mobile-friendly.",
            'objections' => [
                'We already have a website',
                'We get enough work from Google and referrals',
                'We will think about it later',
            ],
            'openingLine' => "I put together a free preview website for your {$industry} business in {$location} — it is ready to look at whenever you have a minute.",
        ];
    }
}

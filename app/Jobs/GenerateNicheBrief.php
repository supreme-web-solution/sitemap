<?php

namespace App\Jobs;

use App\Models\NicheBrief;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
     * Fill a practical research brief from the industry and location (rule-based, no invented stats).
     */
    public function handle(): void
    {
        $industry = $this->brief->industry;
        $location = $this->brief->location;

        $this->brief->forceFill([
            'status' => 'done',
            'error' => null,
            'brief' => [
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
            ],
        ])->save();
    }
}

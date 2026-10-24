<?php

namespace App\Jobs;

use App\Enums\LeadActivityType;
use App\Models\Lead;
use App\Services\Prospecting\WebsiteProfileEnricher;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichLeadFromWebsite implements ShouldQueue
{
    use Queueable;

    public function __construct(public Lead $lead)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    public function handle(WebsiteProfileEnricher $enricher): void
    {
        if (blank($this->lead->website)) {
            return;
        }

        try {
            $result = $enricher->enrich($this->lead);
        } catch (\Throwable) {
            return;
        }

        if ($result['applied'] === []) {
            return;
        }

        $this->lead->recordActivity(
            LeadActivityType::ProfileEnriched,
            __('Enriched from website: :fields', ['fields' => implode(', ', $result['applied'])]),
        );
    }
}

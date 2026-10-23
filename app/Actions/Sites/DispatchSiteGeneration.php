<?php

namespace App\Actions\Sites;

use App\Enums\SiteGenerationStatus;
use App\Jobs\FetchLeadReviews;
use App\Jobs\GenerateLeadSite;
use App\Models\Lead;

class DispatchSiteGeneration
{
    /**
     * Queue the website build, collecting Google reviews first when they have not been fetched yet.
     */
    public function handle(Lead $lead): void
    {
        if (! $this->shouldFetchReviews($lead)) {
            GenerateLeadSite::dispatch($lead);

            return;
        }

        $lead->update([
            'reviews_fetch_status' => SiteGenerationStatus::Queued,
            'reviews_fetch_error' => null,
        ]);

        FetchLeadReviews::dispatch($lead, thenGenerate: true);
    }

    /**
     * Reviews are fetched once, and only for businesses with enough of them to show.
     */
    protected function shouldFetchReviews(Lead $lead): bool
    {
        return filled(config('services.mindcase.key'))
            && $lead->reviews_fetched_at === null
            && $lead->reviews_count >= Lead::MIN_TESTIMONIALS;
    }
}

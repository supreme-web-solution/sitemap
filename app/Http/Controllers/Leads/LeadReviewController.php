<?php

namespace App\Http\Controllers\Leads;

use App\Enums\SiteGenerationStatus;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\UpdateLeadReviewRequest;
use App\Jobs\FetchLeadReviews;
use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadReviewController extends Controller
{
    /**
     * Queue a fetch of the business's Google reviews.
     */
    public function store(Team $currentTeam, Lead $lead, UsageMeter $usage): RedirectResponse
    {
        if (blank(config('services.mindcase.key'))) {
            throw ValidationException::withMessages([
                'reviews' => __('The lead finder API key is not configured, so reviews cannot be fetched.'),
            ]);
        }

        $usage->consume($currentTeam, UsageMetric::ReviewFetches);

        $lead->update([
            'reviews_fetch_status' => SiteGenerationStatus::Queued,
            'reviews_fetch_error' => null,
        ]);

        FetchLeadReviews::dispatch($lead);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fetching Google reviews now.')]);

        return back();
    }

    /**
     * Show or hide a review on the website.
     */
    public function update(UpdateLeadReviewRequest $request, Team $currentTeam, Lead $lead, LeadReview $review): RedirectResponse
    {
        $review->update(['is_visible' => $request->boolean('is_visible')]);

        return back();
    }
}

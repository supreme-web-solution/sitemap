<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadSearchStatus;
use App\Enums\UsageMetric;
use App\Enums\WebsiteFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreLeadImportRequest;
use App\Jobs\RunLeadSearch;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadImportController extends Controller
{
    /**
     * Import a single business from a pasted Google Maps link.
     */
    public function store(StoreLeadImportRequest $request, Team $currentTeam, UsageMeter $usage): RedirectResponse
    {
        $usage->consume($currentTeam, UsageMetric::LeadSearches);

        $search = $currentTeam->leadSearches()->create([
            'place_url' => $request->validated('maps_url'),
            'website_filter' => WebsiteFilter::AllPlaces,
            'max_results' => 1,
            'user_id' => $request->user()->id,
            'status' => LeadSearchStatus::Pending,
        ]);

        RunLeadSearch::dispatch($search);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Importing the business. It will appear shortly.')]);

        return to_route('leads.index', ['current_team' => $currentTeam->slug]);
    }
}

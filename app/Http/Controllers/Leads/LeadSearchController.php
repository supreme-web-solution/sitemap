<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadSearchStatus;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreLeadSearchRequest;
use App\Jobs\RunLeadSearch;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadSearchController extends Controller
{
    /**
     * Start a new Google Maps lead search for the team.
     */
    public function store(StoreLeadSearchRequest $request, Team $currentTeam, UsageMeter $usage): RedirectResponse
    {
        $data = $request->validated();
        $locations = collect([$data['location'], ...($data['locations'] ?? [])])
            ->map(fn (string $location) => trim($location))
            ->filter()
            ->unique()
            ->take(10)
            ->values();

        unset($data['locations']);

        $usage->consume($currentTeam, UsageMetric::LeadSearches, $locations->count());

        foreach ($locations as $location) {
            $search = $currentTeam->leadSearches()->create([
                ...$data,
                'location' => $location,
                'user_id' => $request->user()->id,
                'status' => LeadSearchStatus::Pending,
            ]);

            RunLeadSearch::dispatch($search);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            'Searching :count location. Results will appear shortly.|Searching :count locations. Results will appear shortly.',
            $locations->count(),
            ['count' => $locations->count()],
        )]);

        return to_route('leads.index', ['current_team' => $currentTeam->slug]);
    }
}

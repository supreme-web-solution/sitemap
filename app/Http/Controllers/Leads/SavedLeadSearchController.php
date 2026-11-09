<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadSearchStatus;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreSavedLeadSearchRequest;
use App\Jobs\RunLeadSearch;
use App\Models\SavedLeadSearch;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SavedLeadSearchController extends Controller
{
    /**
     * Save search criteria so the team can re-run it later.
     */
    public function store(StoreSavedLeadSearchRequest $request, Team $currentTeam): RedirectResponse
    {
        $currentTeam->savedLeadSearches()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved search ready to re-run anytime.')]);

        return back();
    }

    /**
     * Start a fresh lead search from a saved query.
     */
    public function run(Team $currentTeam, SavedLeadSearch $savedLeadSearch, UsageMeter $usage): RedirectResponse
    {
        abort_unless($savedLeadSearch->team_id === $currentTeam->id, 404);

        $usage->consume($currentTeam, UsageMetric::LeadSearches, 1);

        $search = $currentTeam->leadSearches()->create([
            ...$savedLeadSearch->criteria(),
            'user_id' => request()->user()->id,
            'status' => LeadSearchStatus::Pending,
        ]);

        RunLeadSearch::dispatch($search);

        $savedLeadSearch->touch();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Searching “:name”. Results will appear shortly.', ['name' => $savedLeadSearch->name]),
        ]);

        return to_route('leads.index', ['current_team' => $currentTeam->slug]);
    }

    /**
     * Remove a saved search.
     */
    public function destroy(Team $currentTeam, SavedLeadSearch $savedLeadSearch): RedirectResponse
    {
        abort_unless($savedLeadSearch->team_id === $currentTeam->id, 404);

        $savedLeadSearch->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved search removed.')]);

        return back();
    }
}

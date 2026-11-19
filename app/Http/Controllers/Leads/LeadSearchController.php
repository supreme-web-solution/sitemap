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

        $emailOnly = $request->boolean('require_email');

        Inertia::flash('toast', ['type' => 'success', 'message' => $emailOnly
            ? __('This search takes longer. It looks up emails, then saves the businesses it finds.')
            : trans_choice(
                'Searching :count location. Results will appear shortly.|Searching :count locations. Results will appear shortly.',
                $locations->count(),
                ['count' => $locations->count()],
            )]);

        return to_route('leads.index', ['current_team' => $currentTeam->slug]);
    }

    /**
     * Remove a search from the recent-search list.
     */
    public function destroy(Team $currentTeam, int $leadSearch): RedirectResponse
    {
        $currentTeam->leadSearches()->whereKey($leadSearch)->delete();

        return $this->redirectToLeads($currentTeam);
    }

    /**
     * Return to the lead list without the search that was just removed.
     *
     * The previous page often still has ?search_id= for that row. Sending the
     * browser back there fails validation once the row is gone.
     */
    protected function redirectToLeads(Team $currentTeam): RedirectResponse
    {
        $leads = route('leads.index', ['current_team' => $currentTeam->slug]);
        $previous = url()->previous();

        if (! str_starts_with($previous, $leads)) {
            return redirect()->to($leads);
        }

        $query = [];
        parse_str((string) parse_url($previous, PHP_URL_QUERY), $query);
        unset($query['search_id']);

        return redirect()->to($query === [] ? $leads : $leads.'?'.http_build_query($query));
    }
}

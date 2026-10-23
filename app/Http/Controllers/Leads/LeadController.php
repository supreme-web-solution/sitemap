<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\WebsiteFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\LeadFilterRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    /**
     * Display the team's leads and recent searches.
     */
    public function index(LeadFilterRequest $request, Team $currentTeam): Response
    {
        $filters = $request->filters();

        $leads = $currentTeam->leads()
            ->with('site:id,lead_id,slug')
            ->filteredBy($filters)
            ->when(isset($filters['ideal']), fn ($query) => $currentTeam->applyIdealCustomerFilter($query))
            ->sortedBy($filters['sort'] ?? null)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Lead $lead) => LeadResource::make($lead)->resolve());

        return Inertia::render('leads/Index', [
            'leads' => $leads,
            'searches' => $currentTeam->leadSearches()
                ->latest()
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (LeadSearch $search) => [
                    'id' => $search->id,
                    'keyword' => $search->keyword,
                    'location' => $search->location,
                    'placeUrl' => $search->place_url,
                    'status' => $search->status->value,
                    'resultsCount' => $search->results_count,
                    'error' => $search->error,
                    'createdAt' => $search->created_at->toISOString(),
                ]),
            'filters' => [
                'status' => $filters['status'] ?? null,
                'search' => $filters['search'] ?? null,
                'sort' => $filters['sort'] ?? 'score',
                'tier' => $filters['tier'] ?? null,
                'follow_up' => $filters['follow_up'] ?? null,
                'assigned' => $filters['assigned'] ?? null,
                'ideal' => $filters['ideal'] ?? null,
            ],
            'websiteFilters' => WebsiteFilter::options(),
            'members' => $currentTeam->memberOptions(),
            'hasIdealCustomer' => filled($currentTeam->ideal_customer),
        ]);
    }

    /**
     * Update the lead's pipeline details.
     */
    public function update(UpdateLeadRequest $request, Team $currentTeam, Lead $lead): RedirectResponse
    {
        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] !== LeadStatus::Lost->value) {
            $validated['lost_reason'] = null;
        }

        $lead->fill($validated);

        $followUpChanged = $lead->isDirty('next_follow_up_at');

        $lead->save();

        if ($followUpChanged && $lead->next_follow_up_at !== null) {
            $lead->recordActivity(
                LeadActivityType::FollowUpScheduled,
                __('Follow-up set for :date', ['date' => $lead->next_follow_up_at->toFormattedDateString()]),
                null,
                $request->user()->id,
            );
        }

        return back();
    }
}

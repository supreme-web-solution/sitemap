<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Leads\DeleteLead;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\UsageMetric;
use App\Enums\WebsiteFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\LeadFilterRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    /**
     * Display the team's leads and recent searches.
     */
    public function index(LeadFilterRequest $request, Team $currentTeam, UsageMeter $usage): Response
    {
        $filters = $request->filters();

        $leads = $currentTeam->leads()
            ->with([
                'site:id,lead_id,team_id,slug',
                'site.team:id,preview_hostname,preview_hostname_verified_at',
                'activeEnrollments.sequence:id,name',
            ])
            ->filteredBy($filters)
            ->when(isset($filters['ideal']), fn ($query) => $currentTeam->applyIdealCustomerFilter($query))
            ->sortedBy($filters['sort'] ?? null)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Lead $lead) => LeadResource::make($lead)->resolve());

        $leadsUsed = $usage->used($currentTeam, UsageMetric::Leads);
        $leadsLimit = $usage->limit($currentTeam, UsageMetric::Leads);

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
                    'leadsCreatedCount' => $search->leads_created_count,
                    'leadsUpdatedCount' => $search->leads_updated_count,
                    'leadsSkippedLimitCount' => $search->leads_skipped_limit_count,
                    'error' => LeadSearch::publicFacingError($search->error),
                    'createdAt' => $search->created_at->toISOString(),
                ]),
            'searchSuggestions' => [
                'keywords' => config('lead-search.keywords'),
                'locations' => config('lead-search.locations'),
            ],
            'leadUsage' => [
                'used' => $leadsUsed,
                'limit' => $leadsLimit,
                'remaining' => $leadsLimit === null ? null : max(0, $leadsLimit - $leadsUsed),
            ],
            'savedSearches' => $currentTeam->savedLeadSearches()
                ->latest('updated_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn ($saved) => [
                    'id' => $saved->id,
                    'name' => $saved->name,
                    'keyword' => $saved->keyword,
                    'location' => $saved->location,
                    'websiteFilter' => $saved->website_filter->value,
                    'minimumRating' => $saved->minimum_rating,
                    'maxResults' => $saved->max_results,
                ]),
            'filters' => [
                'status' => $filters['status'] ?? null,
                'search' => $filters['search'] ?? null,
                'sort' => $filters['sort'] ?? 'score',
                'tier' => $filters['tier'] ?? null,
                'follow_up' => $filters['follow_up'] ?? null,
                'assigned' => $filters['assigned'] ?? null,
                'ideal' => $filters['ideal'] ?? null,
                'search_id' => isset($filters['search_id']) ? (int) $filters['search_id'] : null,
            ],
            'websiteFilters' => WebsiteFilter::options(),
            'members' => $currentTeam->memberOptions(),
            'hasIdealCustomer' => filled($currentTeam->ideal_customer),
            'sequences' => $currentTeam->followUpSequences()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
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

    /**
     * Permanently delete the lead, its website, and the other records saved for it.
     */
    public function destroy(Team $currentTeam, Lead $lead, DeleteLead $deleteLead): RedirectResponse
    {
        $name = $lead->name;
        $deleteLead->handle($lead);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name and everything saved for it.', ['name' => $name]),
        ]);

        return to_route('leads.index', $currentTeam);
    }
}

<?php

namespace App\Http\Controllers\Teams;

use App\Enums\WebsiteFilter;
use App\Http\Controllers\Controller;
use App\Jobs\RescoreTeamLeads;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Prospecting\OpportunityScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IdealCustomerController extends Controller
{
    /**
     * The filters used to rank saved leads against the agency's ideal customer.
     */
    public function edit(Team $currentTeam, OpportunityScorer $scorer): Response
    {
        $weights = $currentTeam->scoreWeights();

        return Inertia::render('ideal-customer/Edit', [
            'ideal' => $currentTeam->ideal_customer ?? [
                'categories' => [],
                'cities' => [],
                'minimumRating' => null,
                'websiteFilter' => WebsiteFilter::WithoutWebsite->value,
            ],
            'websiteFilters' => WebsiteFilter::options(),
            'scoreWeights' => $weights,
            'weightLabels' => [
                'website_need' => 'Website need',
                'website_quality' => 'Website quality',
                'business_activity' => 'Business activity',
                'contactability' => 'Contact details',
                'profile_fit' => 'Profile completeness',
            ],
            'example' => $this->exampleBreakdown($currentTeam, $scorer),
        ]);
    }

    /**
     * Save the ideal customer definition.
     */
    public function update(Request $request, Team $currentTeam): RedirectResponse
    {
        $data = $request->validate([
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['string', 'max:80'],
            'cities' => ['nullable', 'array', 'max:20'],
            'cities.*' => ['string', 'max:80'],
            'minimumRating' => ['nullable', 'numeric', Rule::in([2, 2.5, 3, 3.5, 4, 4.5])],
            'websiteFilter' => ['nullable', Rule::enum(WebsiteFilter::class)],
            'score_weights' => ['required', 'array'],
            'score_weights.website_need' => ['required', 'integer', 'min:0', 'max:100'],
            'score_weights.website_quality' => ['required', 'integer', 'min:0', 'max:100'],
            'score_weights.business_activity' => ['required', 'integer', 'min:0', 'max:100'],
            'score_weights.contactability' => ['required', 'integer', 'min:0', 'max:100'],
            'score_weights.profile_fit' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $weights = collect(OpportunityScorer::WEIGHT_KEYS)
            ->mapWithKeys(fn (string $key) => [$key => (int) $data['score_weights'][$key]])
            ->all();

        if (array_sum($weights) !== 100) {
            return back()->withErrors(['score_weights' => __('The five weights must add up to 100.')]);
        }

        $changedWeights = $currentTeam->scoreWeights() !== $weights;

        $currentTeam->forceFill([
            'ideal_customer' => [
                'categories' => array_values($data['categories'] ?? []),
                'cities' => array_values($data['cities'] ?? []),
                'minimumRating' => $data['minimumRating'] ?? null,
                'websiteFilter' => $data['websiteFilter'] ?? null,
            ],
            'score_weights' => $weights,
        ])->save();

        if ($changedWeights) {
            RescoreTeamLeads::dispatch($currentTeam);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ideal customer and scoring saved. Matching leads will use the new weights.')]);

        return back();
    }

    /**
     * @return array{score: int, breakdown: list<array{key: string, label: string, points: int, max: int, reason: string}>}
     */
    protected function exampleBreakdown(Team $team, OpportunityScorer $scorer): array
    {
        $sample = new Lead([
            'website' => null,
            'phone' => '555-0100',
            'email' => 'owner@example.com',
            'address' => '1 Main St',
            'category' => 'Plumber',
            'city' => 'Austin',
            'rating' => 4.8,
            'reviews_count' => 80,
        ]);
        $sample->setRelation('team', $team);

        return $scorer->score($sample);
    }
}

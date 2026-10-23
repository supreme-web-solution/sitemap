<?php

namespace App\Http\Controllers\Teams;

use App\Enums\WebsiteFilter;
use App\Http\Controllers\Controller;
use App\Models\Team;
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
    public function edit(Team $currentTeam): Response
    {
        return Inertia::render('ideal-customer/Edit', [
            'ideal' => $currentTeam->ideal_customer ?? [
                'categories' => [],
                'cities' => [],
                'minimumRating' => null,
                'websiteFilter' => WebsiteFilter::WithoutWebsite->value,
            ],
            'websiteFilters' => WebsiteFilter::options(),
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
        ]);

        $currentTeam->forceFill([
            'ideal_customer' => [
                'categories' => array_values($data['categories'] ?? []),
                'cities' => array_values($data['cities'] ?? []),
                'minimumRating' => $data['minimumRating'] ?? null,
                'websiteFilter' => $data['websiteFilter'] ?? null,
            ],
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ideal customer saved. The lead list can now filter to matches.')]);

        return back();
    }
}

<?php

namespace App\Actions\Leads;

use App\Data\BusinessListing;
use App\Enums\LeadStatus;
use App\Enums\UsageMetric;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Services\Billing\UsageMeter;
use Illuminate\Support\Collection;

class StoreLeadsFromListings
{
    public function __construct(private UsageMeter $usage)
    {
    }

    /**
     * Columns refreshed when a lead the team already has is found again.
     *
     * Pipeline fields (status, deal value, notes) are intentionally kept, and
     * leads whose details the owner confirmed are not refreshed at all.
     *
     * @var list<string>
     */
    protected const REFRESHED_COLUMNS = [
        'name', 'category', 'phone', 'email', 'website', 'address', 'city', 'state', 'country',
        'latitude', 'longitude', 'rating', 'reviews_count', 'opening_hours', 'description',
        'photo_url', 'maps_url', 'updated_at',
    ];

    /**
     * Insert or refresh the team's leads from the given listings.
     *
     * @param  list<BusinessListing>  $listings
     */
    public function handle(LeadSearch $search, array $listings): void
    {
        $listings = collect($listings)->unique('placeId');

        $confirmedPlaceIds = Lead::query()
            ->where('team_id', $search->team_id)
            ->whereIn('place_id', $listings->pluck('placeId'))
            ->whereNotNull('details_confirmed_at')
            ->pluck('place_id')
            ->all();

        $rows = $listings
            ->reject(fn (BusinessListing $listing) => in_array($listing->placeId, $confirmedPlaceIds, true))
            ->map(fn (BusinessListing $listing) => [
                'team_id' => $search->team_id,
                'lead_search_id' => $search->id,
                'place_id' => $listing->placeId,
                'name' => $listing->name,
                'category' => $listing->category,
                'phone' => $listing->phone,
                'email' => $listing->email,
                'website' => $listing->website,
                'address' => $listing->address,
                'city' => $listing->city,
                'state' => $listing->state,
                'country' => $listing->country,
                'latitude' => $listing->latitude,
                'longitude' => $listing->longitude,
                'rating' => $listing->rating,
                'reviews_count' => $listing->reviewsCount,
                'opening_hours' => $listing->openingHours === null ? null : json_encode($listing->openingHours),
                'description' => $listing->description,
                'photo_url' => $listing->photoUrl,
                'maps_url' => $listing->mapsUrl,
                'status' => LeadStatus::New->value,
            ])
            ->values();

        $rows = $this->withinLeadAllowance($search, $rows);

        $rows->chunk(200)->each(fn ($chunk) => Lead::upsert(
            $chunk->values()->all(),
            ['team_id', 'place_id'],
            self::REFRESHED_COLUMNS,
        ));

        $this->scoreStoredLeads($search, $rows->pluck('place_id')->all());
    }

    /**
     * Keep leads the team already has, and add new ones only up to what the plan allows.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function withinLeadAllowance(LeadSearch $search, Collection $rows): Collection
    {
        $remaining = $this->usage->remaining($search->team, UsageMetric::Leads);

        if ($remaining === null) {
            return $rows;
        }

        $existing = Lead::query()
            ->where('team_id', $search->team_id)
            ->whereIn('place_id', $rows->pluck('place_id'))
            ->pluck('place_id')
            ->all();

        $kept = 0;

        return $rows->filter(function (array $row) use ($existing, $remaining, &$kept) {
            if (in_array($row['place_id'], $existing, true)) {
                return true;
            }

            return $kept++ < $remaining;
        })->values();
    }

    /**
     * Upserts skip model events, so score the stored leads explicitly.
     *
     * @param  list<string>  $placeIds
     */
    protected function scoreStoredLeads(LeadSearch $search, array $placeIds): void
    {
        collect($placeIds)->chunk(200)->each(function ($chunk) use ($search) {
            Lead::query()
                ->where('team_id', $search->team_id)
                ->whereIn('place_id', $chunk->all())
                ->get()
                ->each(function (Lead $lead) {
                    $lead->opportunity_score = null;
                    $lead->save();
                });
        });
    }
}

<?php

namespace App\Actions\Leads;

use App\Data\BusinessListing;
use App\Enums\AutomationTrigger;
use App\Enums\LeadStatus;
use App\Enums\UsageMetric;
use App\Jobs\RunAgencyWorkflow;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Services\Billing\UsageMeter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StoreLeadsFromListings
{
    public function __construct(private UsageMeter $usage) {}

    /**
     * Columns refreshed when a lead the team already has is found again.
     *
     * Pipeline fields (status, deal value, notes) are intentionally kept, and
     * leads whose details the owner confirmed are not refreshed at all.
     *
     * @var list<string>
     */
    protected const REFRESHED_COLUMNS = [
        'lead_search_id', 'name', 'category', 'phone', 'additional_phones', 'email', 'social_links', 'website', 'address', 'city', 'neighborhood',
        'located_in', 'state', 'country', 'latitude', 'longitude', 'rating', 'reviews_count', 'opening_hours', 'description',
        'listing_services', 'photo_url', 'listing_photo_urls', 'maps_url', 'updated_at',
    ];

    /**
     * Max lengths for listing fields before they hit the database.
     *
     * VARCHAR columns stay at 255. Long URL/address fields use text and are
     * still capped so a bad provider row cannot blow up an upsert batch.
     */
    protected const FIELD_LIMITS = [
        'place_id' => 255,
        'name' => 255,
        'category' => 255,
        'phone' => 255,
        'email' => 255,
        'website' => 2048,
        'address' => 2048,
        'city' => 255,
        'neighborhood' => 255,
        'located_in' => 255,
        'state' => 255,
        'country' => 255,
        'photo_url' => 2048,
        'maps_url' => 2048,
        'description' => 65000,
    ];

    /**
     * Insert or refresh the team's leads from the given listings.
     *
     * @param  list<BusinessListing>  $listings
     * @return array{found: int, created: int, updated: int, skipped_confirmed: int, skipped_limit: int, remaining_before: int|null}
     */
    public function handle(LeadSearch $search, array $listings): array
    {
        $listings = collect($listings)->unique('placeId');
        $found = $listings->count();

        $existingPlaceIds = Lead::query()
            ->where('team_id', $search->team_id)
            ->whereIn('place_id', $listings->pluck('placeId'))
            ->pluck('place_id')
            ->all();

        $confirmedPlaceIds = Lead::query()
            ->where('team_id', $search->team_id)
            ->whereIn('place_id', $listings->pluck('placeId'))
            ->whereNotNull('details_confirmed_at')
            ->pluck('place_id')
            ->all();

        $remainingBefore = $this->usage->remaining($search->team, UsageMetric::Leads);
        $now = now();

        $rows = $listings
            ->reject(fn (BusinessListing $listing) => in_array($listing->placeId, $confirmedPlaceIds, true))
            ->map(fn (BusinessListing $listing) => $this->rowFromListing($search, $listing, $now))
            ->filter(fn (array $row) => filled($row['place_id']) && filled($row['name']))
            ->values();

        $candidateCount = $rows->count();
        $rows = $this->withinLeadAllowance($search, $rows, $remainingBefore);
        $skippedLimit = max(0, $candidateCount - $rows->count());

        $rows->chunk(200)->each(fn ($chunk) => Lead::upsert(
            $chunk->values()->all(),
            ['team_id', 'place_id'],
            self::REFRESHED_COLUMNS,
        ));

        $placeIds = $rows->pluck('place_id')->all();
        $this->scoreStoredLeads($search, $placeIds);

        $newPlaceIds = array_values(array_diff($placeIds, $existingPlaceIds));
        $updatedPlaceIds = array_values(array_intersect($placeIds, $existingPlaceIds));

        if ($newPlaceIds !== []) {
            Lead::query()
                ->where('team_id', $search->team_id)
                ->whereIn('place_id', $newPlaceIds)
                ->each(fn (Lead $lead) => RunAgencyWorkflow::dispatchForLead($lead, AutomationTrigger::LeadCreated));
        }

        return [
            'found' => $found,
            'created' => count($newPlaceIds),
            'updated' => count($updatedPlaceIds),
            'skipped_confirmed' => count($confirmedPlaceIds),
            'skipped_limit' => $skippedLimit,
            'remaining_before' => $remainingBefore,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rowFromListing(LeadSearch $search, BusinessListing $listing, mixed $now): array
    {
        return [
            'team_id' => $search->team_id,
            'lead_search_id' => $search->id,
            'place_id' => $this->limitString($listing->placeId, self::FIELD_LIMITS['place_id']),
            'name' => $this->limitString($listing->name, self::FIELD_LIMITS['name']),
            'category' => $this->limitString($listing->category, self::FIELD_LIMITS['category']),
            'phone' => $this->limitString($listing->phone, self::FIELD_LIMITS['phone']),
            'additional_phones' => $listing->additionalPhones === [] ? null : json_encode($listing->additionalPhones),
            'email' => $this->limitString($listing->email, self::FIELD_LIMITS['email']),
            'social_links' => $listing->socialLinks === [] ? null : json_encode($listing->socialLinks),
            'website' => $this->limitString($listing->website, self::FIELD_LIMITS['website']),
            'address' => $this->limitString($listing->address, self::FIELD_LIMITS['address']),
            'city' => $this->limitString($listing->city, self::FIELD_LIMITS['city']),
            'neighborhood' => $this->limitString($listing->neighborhood, self::FIELD_LIMITS['neighborhood']),
            'located_in' => $this->limitString($listing->locatedIn, self::FIELD_LIMITS['located_in']),
            'state' => $this->limitString($listing->state, self::FIELD_LIMITS['state']),
            'country' => $this->limitString($listing->country, self::FIELD_LIMITS['country']),
            'latitude' => $listing->latitude,
            'longitude' => $listing->longitude,
            'rating' => $listing->rating,
            'reviews_count' => $listing->reviewsCount,
            'opening_hours' => $listing->openingHours === null ? null : json_encode($listing->openingHours),
            'description' => $this->limitString($listing->description, self::FIELD_LIMITS['description']),
            'listing_services' => $listing->services === [] ? null : json_encode($listing->services),
            'photo_url' => $this->limitString($listing->photoUrl, self::FIELD_LIMITS['photo_url']),
            'listing_photo_urls' => $listing->photoUrls === [] ? null : json_encode($listing->photoUrls),
            'maps_url' => $this->limitString($listing->mapsUrl, self::FIELD_LIMITS['maps_url']),
            'status' => LeadStatus::New->value,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    protected function limitString(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $max, '');
    }

    /**
     * Keep leads the team already has, and add new ones only up to what the plan allows.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function withinLeadAllowance(LeadSearch $search, Collection $rows, ?int $remaining): Collection
    {
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

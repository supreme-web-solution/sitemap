<?php

use App\Actions\Leads\StoreLeadsFromListings;
use App\Data\BusinessListing;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Services\Prospecting\OpportunityScorer;

test('a busy business with no website and full contact details scores as a hot lead', function () {
    $lead = Lead::factory()->create([
        'website' => null,
        'phone' => '555-0100',
        'email' => 'owner@example.com',
        'address' => '1 Main St',
        'category' => 'Plumber',
        'city' => 'Austin',
        'rating' => 4.8,
        'reviews_count' => 250,
    ]);

    expect($lead->opportunity_score)->toBeGreaterThanOrEqual(90);
    expect(OpportunityScorer::tier($lead->opportunity_score))->toBe('hot');

    expect(collect($lead->opportunity_breakdown)->sum('points'))->toBe($lead->opportunity_score);
});

test('a business with a good website scores lower than one without a website', function () {
    $without = Lead::factory()->create(['website' => null, 'reviews_count' => 100, 'rating' => 4.5]);
    $with = Lead::factory()->create([
        'website' => 'https://good.example',
        'reviews_count' => 100,
        'rating' => 4.5,
        'website_audit' => ['score' => 95, 'error' => null, 'checks' => []],
    ]);

    expect($with->opportunity_score)->toBeLessThan($without->opportunity_score);
});

test('a website with a poor audit scores higher than one that has not been audited', function () {
    $unaudited = Lead::factory()->create(['website' => 'https://a.example']);
    $poor = Lead::factory()->create([
        'website' => 'https://b.example',
        'website_audit' => ['score' => 20, 'error' => null, 'checks' => []],
    ]);

    expect($poor->opportunity_score)->toBeGreaterThan($unaudited->opportunity_score);
});

test('the score is recalculated when a scored attribute changes', function () {
    $lead = Lead::factory()->create(['website' => null, 'phone' => null, 'email' => null]);
    $before = $lead->opportunity_score;

    $lead->update(['phone' => '555-0100', 'email' => 'a@example.com']);

    expect($lead->fresh()->opportunity_score)->toBeGreaterThan($before);
});

test('leads stored in bulk from a search are scored too', function () {
    $search = LeadSearch::factory()->create();

    app(StoreLeadsFromListings::class)->handle($search, [
        new BusinessListing(
            placeId: 'ChIJbulk0000000001',
            name: 'Bulk Plumbing',
            category: 'Plumber',
            phone: '555-0100',
            email: null,
            website: null,
            address: '1 Main St',
            city: 'Austin',
            state: 'TX',
            country: 'US',
            latitude: null,
            longitude: null,
            rating: 4.7,
            reviewsCount: 120,
            openingHours: null,
            description: null,
            photoUrl: null,
            mapsUrl: null,
        ),
    ]);

    expect(Lead::where('place_id', 'ChIJbulk0000000001')->first()->opportunity_score)->toBeGreaterThan(60);
});

<?php

use App\Actions\Leads\StoreLeadsFromListings;
use App\Enums\LeadSearchStatus;
use App\Enums\LeadStatus;
use App\Jobs\RunLeadSearch;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Services\LeadFinder\LeadFinder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Build a Mindcase Google Maps place row.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mindcasePlace(array $overrides = []): array
{
    return [
        'placeId' => 'ChIJ-austin-pro-plumbing',
        'businessName' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'phone' => '+15125550192',
        'emails' => ['hello@austinpro.test'],
        'website' => null,
        'address' => '100 Congress Ave, Austin, TX 78701',
        'city' => 'Austin',
        'state' => 'Texas',
        'country' => 'US',
        'latitude' => 30.2672,
        'longitude' => -97.7431,
        'rating' => 4.9,
        'reviews' => 142,
        'openingHours' => ['Monday' => '8 AM to 6 PM', 'Sunday' => 'Closed'],
        'coverPhoto' => 'https://example.test/photo.jpg',
        'placeUrl' => 'https://maps.google.com/?cid=1',
        'permanentlyClosed' => false,
        ...$overrides,
    ];
}

function runLeadSearch(LeadSearch $search): RunLeadSearch
{
    $job = (new RunLeadSearch($search))->withFakeQueueInteractions();

    $job->handle(app(LeadFinder::class), app(StoreLeadsFromListings::class));

    return $job;
}

test('a completed search stores open businesses as team leads', function () {
    config(['services.mindcase.key' => 'mk_test_key']);
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'job_id' => 'job-1',
            'status' => 'completed',
            'truncated' => false,
            'data' => [
                mindcasePlace(),
                mindcasePlace(['placeId' => 'ChIJ-closed', 'businessName' => 'Closed Co', 'permanentlyClosed' => true]),
            ],
        ]),
    ]);

    $search = LeadSearch::factory()->create(['keyword' => 'plumbers', 'location' => 'Austin, Texas', 'minimum_rating' => 4.5]);

    runLeadSearch($search)->assertNotReleased();

    expect($search->fresh())
        ->status->toBe(LeadSearchStatus::Completed)
        ->results_count->toBe(1)
        ->provider_job_id->toBe('job-1')
        ->completed_at->not->toBeNull();

    $lead = $search->team->leads()->sole();

    expect($lead)
        ->place_id->toBe('ChIJ-austin-pro-plumbing')
        ->name->toBe('Austin Pro Plumbing')
        ->email->toBe('hello@austinpro.test')
        ->rating->toBe(4.9)
        ->reviews_count->toBe(142)
        ->opening_hours->toBe(['Monday' => '8 AM to 6 PM', 'Sunday' => 'Closed'])
        ->photo_url->toBe('https://example.test/photo.jpg')
        ->lead_search_id->toBe($search->id)
        ->status->toBe(LeadStatus::New);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer mk_test_key')
        && $request['params'] === [
            'keywords' => 'plumbers',
            'location' => 'Austin, Texas',
            'maxResults' => 20,
            'websiteFilter' => 'withoutWebsite',
            'minimumRating' => 'fourAndHalf',
        ]);
});

test('a search that is still running is polled again later', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response(['job_id' => 'job-2', 'status' => 'running']),
    ]);

    $search = LeadSearch::factory()->create();

    runLeadSearch($search)->assertReleased(delay: RunLeadSearch::POLL_DELAY);

    expect($search->fresh())
        ->status->toBe(LeadSearchStatus::Running)
        ->provider_job_id->toBe('job-2');
});

test('polling a running search stores leads once the provider finishes', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/jobs/job-3/results' => Http::response([
            'status' => 'completed',
            'data' => [mindcasePlace()],
        ]),
    ]);

    $search = LeadSearch::factory()->running('job-3')->create();

    runLeadSearch($search)->assertNotReleased();

    expect($search->fresh()->status)->toBe(LeadSearchStatus::Completed);
    expect($search->team->leads()->count())->toBe(1);
});

test('finding a business again refreshes its details but keeps its pipeline stage', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'job_id' => 'job-4',
            'status' => 'completed',
            'data' => [mindcasePlace(['reviews' => 150])],
        ]),
    ]);

    $search = LeadSearch::factory()->create();
    $existing = Lead::factory()->create([
        'team_id' => $search->team_id,
        'place_id' => 'ChIJ-austin-pro-plumbing',
        'reviews_count' => 100,
        'status' => LeadStatus::Contacted,
        'notes' => 'Called on Monday',
    ]);

    runLeadSearch($search);

    expect($existing->fresh())
        ->reviews_count->toBe(150)
        ->status->toBe(LeadStatus::Contacted)
        ->notes->toBe('Called on Monday');
    expect(Lead::count())->toBe(1);
});

test('a pasted maps link is looked up directly', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'job_id' => 'job-7',
            'status' => 'completed',
            'data' => [mindcasePlace()],
        ]),
    ]);

    $search = LeadSearch::factory()->create([
        'keyword' => null,
        'location' => null,
        'place_url' => 'https://maps.app.goo.gl/abc123',
        'max_results' => 1,
    ]);

    runLeadSearch($search);

    expect($search->team->leads()->sole()->name)->toBe('Austin Pro Plumbing');
    Http::assertSent(fn (Request $request) => $request['params'] === ['urls' => ['https://maps.app.goo.gl/abc123']]);
});

test('finding a business again keeps the details its owner confirmed', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'job_id' => 'job-8',
            'status' => 'completed',
            'data' => [mindcasePlace()],
        ]),
    ]);

    $search = LeadSearch::factory()->create();
    $confirmed = Lead::factory()->create([
        'team_id' => $search->team_id,
        'place_id' => 'ChIJ-austin-pro-plumbing',
        'name' => 'Austin Pro Plumbing & Drains',
        'details_confirmed_at' => now(),
    ]);

    runLeadSearch($search);

    expect($confirmed->fresh()->name)->toBe('Austin Pro Plumbing & Drains');
    expect(Lead::count())->toBe(1);
});

test('the same business found by another team becomes a separate lead', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'job_id' => 'job-5',
            'status' => 'completed',
            'data' => [mindcasePlace()],
        ]),
    ]);

    $otherTeamLead = Lead::factory()->create(['place_id' => 'ChIJ-austin-pro-plumbing']);
    $search = LeadSearch::factory()->create();

    runLeadSearch($search);

    expect($search->team->leads()->count())->toBe(1);
    expect($otherTeamLead->fresh()->lead_search_id)->toBeNull();
});

test('a rejected api key fails the search with a helpful message', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $search = LeadSearch::factory()->create();

    runLeadSearch($search)->assertNotReleased();

    expect($search->fresh())
        ->status->toBe(LeadSearchStatus::Failed)
        ->error->toBe('The lead finder API key is missing or invalid.');
});

test('an empty provider wallet fails the search', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/jobs/job-6/results' => Http::response(['status' => 'rejected_balance']),
    ]);

    $search = LeadSearch::factory()->running('job-6')->create();

    runLeadSearch($search);

    expect($search->fresh())
        ->status->toBe(LeadSearchStatus::Failed)
        ->error->toBe('Your Mindcase wallet balance is too low to run this search.');
});

test('a finished search is not sent to the provider again', function () {
    Http::preventStrayRequests();

    $search = LeadSearch::factory()->completed()->create();

    runLeadSearch($search)->assertNotReleased();

    Http::assertNothingSent();
});

test('a search that exhausts its retries is marked as failed', function () {
    $search = LeadSearch::factory()->running()->create();

    (new RunLeadSearch($search))->failed(new RuntimeException('Timed out'));

    expect($search->fresh())
        ->status->toBe(LeadSearchStatus::Failed)
        ->error->toBe('The search timed out. Please try again.');
});

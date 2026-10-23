<?php

use App\Actions\Reviews\StoreLeadReviews;
use App\Actions\Sites\DispatchSiteGeneration;
use App\Data\PlaceReview;
use App\Enums\SiteGenerationStatus;
use App\Jobs\FetchLeadReviews;
use App\Jobs\GenerateLeadSite;
use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\User;
use App\Services\ReviewFinder\ReviewFinder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Build a Mindcase Google Maps review row.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mindcaseReviewRow(array $overrides = []): array
{
    return [
        'reviewUrl' => 'https://maps.google.com/?review='.fake()->unique()->uuid(),
        'reviewer' => fake()->name(),
        'stars' => 5,
        'reviewText' => 'Brilliant service from start to finish, would recommend to anyone.',
        'postedAt' => '2 months ago',
        'originalLanguage' => 'en',
        ...$overrides,
    ];
}

function runFetchLeadReviews(Lead $lead, bool $thenGenerate = false): FetchLeadReviews
{
    $job = (new FetchLeadReviews($lead, $thenGenerate))->withFakeQueueInteractions();

    $job->handle(app(ReviewFinder::class), app(StoreLeadReviews::class));

    return $job;
}

beforeEach(function () {
    config(['services.mindcase.key' => 'mk_test_key']);
});

test('the review finder asks mindcase for the places reviews and keeps only reviews with text', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/reviews/run*' => Http::response([
            'job_id' => 'reviews-1',
            'status' => 'completed',
            'truncated' => false,
            'data' => [
                mindcaseReviewRow(['reviewer' => 'Sam Carter']),
                mindcaseReviewRow(['reviewText' => '', 'translatedText' => '']),
                mindcaseReviewRow(['reviewer' => '']),
                mindcaseReviewRow([
                    'reviewer' => 'Marta Gomez',
                    'reviewText' => 'Un servicio excelente y muy rapido.',
                    'originalLanguage' => 'es',
                    'translatedText' => 'An excellent and very fast service.',
                ]),
            ],
        ]),
    ]);

    $lead = Lead::factory()->create(['maps_url' => 'https://maps.google.com/?cid=42']);

    $result = app(ReviewFinder::class)->start($lead);

    expect($result->reviews)->toHaveCount(2)
        ->and($result->reviews[0]->authorName)->toBe('Sam Carter')
        ->and($result->reviews[1]->body)->toBe('An excellent and very fast service.');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer mk_test_key')
        && $request['params']['urls'] === [['url' => 'https://maps.google.com/?cid=42']]
        && $request['params']['maxReviews'] === config('prospecting.reviews.fetch_limit'));
});

test('a completed fetch keeps only strong written reviews and marks the lead as fetched', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/reviews/run*' => Http::response([
            'job_id' => 'reviews-1',
            'status' => 'completed',
            'data' => [
                mindcaseReviewRow(['reviewer' => 'Five Star', 'stars' => 5]),
                mindcaseReviewRow(['reviewer' => 'Four Star', 'stars' => 4]),
                mindcaseReviewRow(['reviewer' => 'Three Star', 'stars' => 3]),
                mindcaseReviewRow(['reviewer' => 'Too Short', 'stars' => 5, 'reviewText' => 'Great!']),
            ],
        ]),
    ]);

    $lead = Lead::factory()->create(['reviews_fetch_status' => SiteGenerationStatus::Queued]);

    runFetchLeadReviews($lead)->assertNotReleased();

    expect($lead->reviews()->orderBy('id')->pluck('author_name')->all())->toBe(['Five Star', 'Four Star'])
        ->and($lead->fresh())
        ->reviews_fetched_at->not->toBeNull()
        ->reviews_fetch_status->toBe(SiteGenerationStatus::Idle)
        ->reviews_fetch_error->toBeNull();
});

test('fetching again updates reviews without un-hiding the ones the owner removed', function () {
    $lead = Lead::factory()->create();
    $row = mindcaseReviewRow(['reviewUrl' => 'https://maps.google.com/?review=stable', 'reviewer' => 'Stable Person']);

    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/reviews/run*' => Http::response(['job_id' => 'r', 'status' => 'completed', 'data' => [$row]]),
    ]);

    runFetchLeadReviews($lead);
    $lead->reviews()->update(['is_visible' => false]);
    runFetchLeadReviews($lead);

    expect($lead->reviews)->toHaveCount(1)
        ->and($lead->reviews->first()->is_visible)->toBeFalse();
});

test('very long reviews are shortened at a word boundary', function () {
    $lead = Lead::factory()->create();

    app(StoreLeadReviews::class)->handle($lead, [
        new PlaceReview('long', 'Long Writer', 5, str_repeat('wonderful ', 80)),
    ]);

    $body = $lead->reviews()->sole()->body;

    expect(mb_strlen($body))->toBeLessThanOrEqual(config('prospecting.reviews.maximum_length'))
        ->and($body)->toEndWith('…');
});

test('a run that is still going is checked again later using the provider job id', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/reviews/run*' => Http::response(['job_id' => 'reviews-9', 'status' => 'running']),
        'api.mindcase.co/v1/jobs/reviews-9/results' => Http::response([
            'status' => 'completed',
            'data' => [mindcaseReviewRow()],
        ]),
    ]);

    $lead = Lead::factory()->create();

    runFetchLeadReviews($lead)->assertReleased(FetchLeadReviews::POLL_DELAY);
    expect($lead->reviews()->count())->toBe(0)
        ->and(Cache::get('reviews-job:'.$lead->id))->toBe('reviews-9');

    runFetchLeadReviews($lead)->assertNotReleased();
    expect($lead->reviews()->count())->toBe(1)
        ->and(Cache::get('reviews-job:'.$lead->id))->toBeNull();
});

test('a rejected fetch records the reason and still builds the site when asked to', function () {
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/reviews/run*' => Http::response(['error' => 'balance'], 402),
    ]);

    $lead = Lead::factory()->create(['reviews_fetch_status' => SiteGenerationStatus::Queued]);

    runFetchLeadReviews($lead, thenGenerate: true);

    expect($lead->fresh())
        ->reviews_fetch_status->toBe(SiteGenerationStatus::Failed)
        ->reviews_fetch_error->toContain('wallet balance');
    Queue::assertPushed(GenerateLeadSite::class, fn (GenerateLeadSite $job) => $job->lead->is($lead));
});

test('building a site fetches reviews first when the business has enough and none were fetched', function () {
    Queue::fake();

    $lead = Lead::factory()->create(['reviews_count' => 40]);

    app(DispatchSiteGeneration::class)->handle($lead);

    Queue::assertPushed(FetchLeadReviews::class, fn (FetchLeadReviews $job) => $job->lead->is($lead) && $job->thenGenerate);
    Queue::assertNotPushed(GenerateLeadSite::class);
    expect($lead->fresh()->reviews_fetch_status)->toBe(SiteGenerationStatus::Queued);
});

test('building a site skips the review fetch when it is not needed', function (array $state, ?string $key) {
    Queue::fake();
    config(['services.mindcase.key' => $key]);

    $lead = Lead::factory()->create($state);

    app(DispatchSiteGeneration::class)->handle($lead);

    Queue::assertPushed(GenerateLeadSite::class);
    Queue::assertNotPushed(FetchLeadReviews::class);
})->with([
    'already fetched' => [['reviews_count' => 40, 'reviews_fetched_at' => now()], 'mk_test_key'],
    'too few reviews' => [['reviews_count' => 1], 'mk_test_key'],
    'no api key' => [['reviews_count' => 40], null],
]);

test('reviews can be fetched on demand and are scoped to the team', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $lead = Lead::factory()->for($team)->create();
    $other = Lead::factory()->create();

    $this->actingAs($user)
        ->post(route('leads.reviews.store', ['current_team' => $team->slug, 'lead' => $lead->id]))
        ->assertRedirect();

    Queue::assertPushed(FetchLeadReviews::class, fn (FetchLeadReviews $job) => $job->lead->is($lead) && ! $job->thenGenerate);
    expect($lead->fresh()->reviews_fetch_status)->toBe(SiteGenerationStatus::Queued);

    $this->actingAs($user)
        ->post(route('leads.reviews.store', ['current_team' => $team->slug, 'lead' => $other->id]))
        ->assertNotFound();
});

test('fetching reviews needs the lead finder api key', function () {
    Queue::fake();
    config(['services.mindcase.key' => null]);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('leads.reviews.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertSessionHasErrors('reviews');

    Queue::assertNothingPushed();
});

test('a review can be hidden from and shown on the website', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $lead = Lead::factory()->for($team)->create();
    $review = LeadReview::factory()->for($lead)->create();

    $this->actingAs($user)
        ->patch(route('leads.reviews.update', ['current_team' => $team->slug, 'lead' => $lead->id, 'review' => $review->id]), ['is_visible' => false])
        ->assertRedirect();

    expect($review->fresh()->is_visible)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('leads.reviews.update', ['current_team' => $team->slug, 'lead' => $lead->id, 'review' => $review->id]), ['is_visible' => true]);

    expect($review->fresh()->is_visible)->toBeTrue();
});

test('reviews of another lead or team cannot be changed', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $lead = Lead::factory()->for($team)->create();
    $otherLeadReview = LeadReview::factory()->for(Lead::factory()->for($team))->create();
    $foreignReview = LeadReview::factory()->create();

    $this->actingAs($user)
        ->patch(route('leads.reviews.update', ['current_team' => $team->slug, 'lead' => $lead->id, 'review' => $otherLeadReview->id]), ['is_visible' => false])
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('leads.reviews.update', ['current_team' => $team->slug, 'lead' => $foreignReview->lead_id, 'review' => $foreignReview->id]), ['is_visible' => false])
        ->assertNotFound();

    expect($otherLeadReview->fresh()->is_visible)->toBeTrue()
        ->and($foreignReview->fresh()->is_visible)->toBeTrue();
});

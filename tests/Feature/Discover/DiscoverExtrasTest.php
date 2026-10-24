<?php

use App\Jobs\GenerateNicheBrief;
use App\Jobs\RunLeadSearch;
use App\Jobs\SummarizeLeadReviews;
use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('one search can cover several locations', function () {
    Queue::fake([RunLeadSearch::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('lead-searches.store', ['current_team' => $user->currentTeam->slug]), [
            'keyword' => 'plumbers',
            'location' => 'Austin, Texas',
            'locations' => ['Dallas, Texas', 'Austin, Texas'],
            'website_filter' => 'withoutWebsite',
            'max_results' => 10,
        ])
        ->assertRedirect();

    expect($user->currentTeam->leadSearches()->pluck('location')->sort()->values()->all())
        ->toBe(['Austin, Texas', 'Dallas, Texas']);

    Queue::assertPushed(RunLeadSearch::class, 2);
});

test('the ideal customer filter keeps matching leads', function () {
    $user = User::factory()->create();
    $user->currentTeam->forceFill([
        'ideal_customer' => [
            'categories' => ['plumber'],
            'cities' => ['Austin'],
            'minimumRating' => 4,
            'websiteFilter' => 'withoutWebsite',
        ],
    ])->save();

    Lead::factory()->for($user->currentTeam)->create(['name' => 'Match Co', 'category' => 'Emergency plumber', 'city' => 'Austin', 'rating' => 4.6, 'website' => null]);
    Lead::factory()->for($user->currentTeam)->withWebsite()->create(['name' => 'Skip Co', 'category' => 'plumber', 'city' => 'Austin', 'rating' => 4.8]);

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $user->currentTeam->slug, 'ideal' => '1']))
        ->assertInertia(fn (Assert $page) => $page->has('leads.data', 1)->where('leads.data.0.name', 'Match Co'));
});

test('review summaries extract overlapping themes', function () {
    $lead = Lead::factory()->create();
    LeadReview::factory()->for($lead)->create(['rating' => 5, 'body' => 'Friendly staff and fast service.']);
    LeadReview::factory()->for($lead)->create(['rating' => 2, 'body' => 'The wait was too long.']);

    (new SummarizeLeadReviews($lead))->handle();

    expect($lead->fresh()->reviews_summary['praise'])->toContain('Friendly staff')
        ->and($lead->fresh()->reviews_summary['complaints'])->toContain('Waiting times');
});

test('niche research is queued and filled in', function () {
    Queue::fake([GenerateNicheBrief::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('research.store', ['current_team' => $user->currentTeam->slug]), [
            'industry' => 'plumbers',
            'location' => 'Austin',
        ])
        ->assertRedirect();

    Queue::assertPushed(GenerateNicheBrief::class);
    expect($user->currentTeam->nicheBriefs()->sole()->industry)->toBe('plumbers');
});

test('a competitor url is audited', function () {
    Http::fake(['93.184.216.34/*' => Http::response('<html><head><title>Hi</title><meta name="viewport" content="width=device-width"></head><body><a href="tel:1">Call</a></body></html>', 200)]);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('leads.competitor-audit.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'url' => 'http://93.184.216.34/',
        ])
        ->assertRedirect();

    expect($lead->fresh()->competitor_audit)->not->toBeNull();
});

test('the research and ideal customer pages render', function () {
    $user = User::factory()->create();
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)
        ->get(route('research.index', ['current_team' => $slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('research/Index')->has('briefs'));

    $this->actingAs($user)
        ->get(route('ideal-customer.edit', ['current_team' => $slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ideal-customer/Edit')
            ->has('ideal')
            ->has('websiteFilters')
            ->has('scoreWeights')
            ->has('example'));
});

test('the reports page shows pipeline and revenue totals', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reports/Index')->has('revenue')->has('lostReasons'));
});

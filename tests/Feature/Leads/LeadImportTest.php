<?php

use App\Enums\LeadSearchStatus;
use App\Jobs\RunLeadSearch;
use App\Models\LeadSearch;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('a business can be imported from a google maps link', function (string $mapsUrl) {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('lead-imports.store', ['current_team' => $user->currentTeam->slug]), ['maps_url' => " {$mapsUrl} "])
        ->assertRedirect(route('leads.index', ['current_team' => $user->currentTeam->slug]));

    $search = LeadSearch::sole();

    expect($search)
        ->team_id->toBe($user->currentTeam->id)
        ->place_url->toBe($mapsUrl)
        ->keyword->toBeNull()
        ->max_results->toBe(1)
        ->status->toBe(LeadSearchStatus::Pending);

    Queue::assertPushed(RunLeadSearch::class, fn (RunLeadSearch $job) => $job->leadSearch->is($search));
})->with([
    'full link' => ['https://www.google.com/maps/place/Austin+Pro+Plumbing/@30.26,-97.74,17z'],
    'short link' => ['https://maps.app.goo.gl/abc123'],
    'place id' => ['ChIJN1t_tDeuEmsRUsoyG83frY4'],
]);

test('only google maps links can be imported', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('lead-imports.store', ['current_team' => $user->currentTeam->slug]), ['maps_url' => 'https://example.com/maps'])
        ->assertSessionHasErrors(['maps_url' => 'Paste a Google Maps link or place ID.']);

    Queue::assertNothingPushed();
});

<?php

use App\Enums\LeadSearchStatus;
use App\Enums\WebsiteFilter;
use App\Jobs\RunLeadSearch;
use App\Models\SavedLeadSearch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('team members can save a named lead search', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('saved-lead-searches.store', ['current_team' => $team->slug]), [
            'name' => 'Lagos window repair',
            'keyword' => 'window repair',
            'location' => 'Lagos, Nigeria',
            'website_filter' => WebsiteFilter::WithoutWebsite->value,
            'minimum_rating' => 4,
            'max_results' => 25,
        ])
        ->assertRedirect();

    $saved = $team->savedLeadSearches()->sole();

    expect($saved)
        ->name->toBe('Lagos window repair')
        ->keyword->toBe('window repair')
        ->location->toBe('Lagos, Nigeria')
        ->website_filter->toBe(WebsiteFilter::WithoutWebsite)
        ->minimum_rating->toBe(4.0)
        ->max_results->toBe(25)
        ->user_id->toBe($user->id);
});

test('saved search names must be unique per team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    SavedLeadSearch::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'name' => 'Austin plumbers',
    ]);

    $this->actingAs($user)
        ->post(route('saved-lead-searches.store', ['current_team' => $team->slug]), [
            'name' => 'Austin plumbers',
            'keyword' => 'plumbers',
            'location' => 'Austin, TX',
            'website_filter' => WebsiteFilter::WithoutWebsite->value,
            'max_results' => 20,
        ])
        ->assertSessionHasErrors('name');
});

test('running a saved search starts a lead search job', function () {
    Queue::fake([RunLeadSearch::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $saved = SavedLeadSearch::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'name' => 'Dentists London',
        'keyword' => 'dentists',
        'location' => 'London',
    ]);

    $this->actingAs($user)
        ->post(route('saved-lead-searches.run', [
            'current_team' => $team->slug,
            'savedLeadSearch' => $saved->id,
        ]))
        ->assertRedirect(route('leads.index', ['current_team' => $team->slug]));

    $search = $team->leadSearches()->sole();

    expect($search)
        ->keyword->toBe('dentists')
        ->location->toBe('London')
        ->status->toBe(LeadSearchStatus::Pending);

    Queue::assertPushed(RunLeadSearch::class);
});

test('users cannot run another team saved search', function () {
    Queue::fake([RunLeadSearch::class]);

    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $saved = SavedLeadSearch::factory()->create(['team_id' => $otherTeam->id]);

    $this->actingAs($user)
        ->post(route('saved-lead-searches.run', [
            'current_team' => $user->currentTeam->slug,
            'savedLeadSearch' => $saved->id,
        ]))
        ->assertNotFound();

    Queue::assertNotPushed(RunLeadSearch::class);
});

test('team members can delete a saved search', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $saved = SavedLeadSearch::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('saved-lead-searches.destroy', [
            'current_team' => $team->slug,
            'savedLeadSearch' => $saved->id,
        ]))
        ->assertRedirect();

    expect(SavedLeadSearch::query()->whereKey($saved->id)->exists())->toBeFalse();
});

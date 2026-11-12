<?php

use App\Enums\LeadSearchStatus;
use App\Jobs\RunLeadSearch;
use App\Models\LeadSearch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

$validSearch = [
    'keyword' => 'plumbers',
    'location' => 'Austin, Texas',
    'website_filter' => 'withoutWebsite',
    'minimum_rating' => 4,
    'max_results' => 25,
];

test('guests cannot start a lead search', function () use ($validSearch) {
    $team = Team::factory()->create();

    $this->post(route('lead-searches.store', ['current_team' => $team->slug]), $validSearch)
        ->assertRedirect(route('login'));
});

test('team members can start a lead search that runs in the background', function () use ($validSearch) {
    Queue::fake([RunLeadSearch::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->post(route('lead-searches.store', ['current_team' => $team->slug]), $validSearch)
        ->assertRedirect(route('leads.index', ['current_team' => $team->slug]));

    $search = $team->leadSearches()->sole();

    expect($search)
        ->keyword->toBe('plumbers')
        ->location->toBe('Austin, Texas')
        ->minimum_rating->toBe(4.0)
        ->max_results->toBe(25)
        ->user_id->toBe($user->id)
        ->status->toBe(LeadSearchStatus::Pending);

    Queue::assertPushed(RunLeadSearch::class, fn (RunLeadSearch $job) => $job->leadSearch->is($search));
});

test('users cannot start a search for a team they do not belong to', function () use ($validSearch) {
    Queue::fake([RunLeadSearch::class]);

    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();

    $this->actingAs($user)
        ->post(route('lead-searches.store', ['current_team' => $otherTeam->slug]), $validSearch)
        ->assertForbidden();

    Queue::assertNotPushed(RunLeadSearch::class);
});

test('lead searches are limited to one hundred results', function () use ($validSearch) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('lead-searches.store', ['current_team' => $user->currentTeam->slug]), [
            ...$validSearch,
            'max_results' => 101,
        ])
        ->assertSessionHasErrors(['max_results' => 'The max results field must not be greater than 100.']);
});

test('lead searches require a keyword and location', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('lead-searches.store', ['current_team' => $user->currentTeam->slug]), [])
        ->assertSessionHasErrors(['keyword', 'location', 'website_filter', 'max_results']);
});

test('the minimum rating must be a supported step', function () use ($validSearch) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('lead-searches.store', ['current_team' => $user->currentTeam->slug]), [
            ...$validSearch,
            'minimum_rating' => 4.2,
        ])
        ->assertSessionHasErrors(['minimum_rating' => 'The selected minimum rating is invalid.']);
});

test('team members can dismiss a recent search from the list', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $search = LeadSearch::factory()->completed()->create([
        'team_id' => $team->id,
        'keyword' => 'construction',
        'location' => 'Dallas, Texas',
    ]);

    $this->actingAs($user)
        ->from(route('leads.index', ['current_team' => $team->slug]))
        ->delete(route('lead-searches.destroy', [
            'current_team' => $team->slug,
            'leadSearch' => $search->id,
        ]))
        ->assertRedirect(route('leads.index', ['current_team' => $team->slug]));

    expect(LeadSearch::query()->whereKey($search->id)->exists())->toBeFalse();
});

test('users cannot dismiss another team search', function () {
    $user = User::factory()->create();
    $search = LeadSearch::factory()->completed()->create();

    $this->actingAs($user)
        ->delete(route('lead-searches.destroy', [
            'current_team' => $user->currentTeam->slug,
            'leadSearch' => $search->id,
        ]))
        ->assertNotFound();

    expect(LeadSearch::query()->whereKey($search->id)->exists())->toBeTrue();
});

<?php

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\Proposal;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the leads page', function () {
    $user = User::factory()->create();

    $this->get(route('leads.index', ['current_team' => $user->currentTeam->slug]))
        ->assertRedirect(route('login'));
});

test('the leads page only lists the current team leads, most reviewed first', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Lead::factory()->create(['team_id' => $team->id, 'name' => 'Few Reviews', 'reviews_count' => 3]);
    Lead::factory()->create(['team_id' => $team->id, 'name' => 'Many Reviews', 'reviews_count' => 300]);
    Lead::factory()->create(['name' => 'Other Team Lead']);
    LeadSearch::factory()->create(['team_id' => $team->id, 'keyword' => 'plumbers']);

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/Index')
            ->has('leads.data', 2)
            ->where('leads.data.0.name', 'Many Reviews')
            ->where('leads.data.1.name', 'Few Reviews')
            ->has('searches', 1)
            ->where('searches.0.keyword', 'plumbers'));
});

test('leads can be filtered by stage and text', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Lead::factory()->create(['team_id' => $team->id, 'name' => 'Won Dental', 'status' => LeadStatus::Won]);
    Lead::factory()->create(['team_id' => $team->id, 'name' => 'Won Plumbing', 'status' => LeadStatus::Won]);
    Lead::factory()->create(['team_id' => $team->id, 'name' => 'New Dental', 'status' => LeadStatus::New]);

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $team->slug, 'status' => 'won', 'search' => 'Dental']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.name', 'Won Dental'));
});

test('a lead stage and deal value can be updated', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);

    $this->actingAs($user)
        ->patch(route('leads.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'status' => 'won',
            'deal_value' => 1500,
        ])
        ->assertRedirect();

    expect($lead->fresh())
        ->status->toBe(LeadStatus::Won)
        ->deal_value->toBe(1500);
});

test('a lead stage must be a known pipeline stage', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);

    $this->actingAs($user)
        ->patch(route('leads.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'status' => 'archived',
        ])
        ->assertSessionHasErrors(['status' => 'The selected status is invalid.']);
});

test('another team lead cannot be updated through your team', function () {
    $user = User::factory()->create();
    $otherLead = Lead::factory()->create(['status' => LeadStatus::New]);

    $this->actingAs($user)
        ->patch(route('leads.update', ['current_team' => $user->currentTeam->slug, 'lead' => $otherLead->id]), [
            'status' => 'won',
        ])
        ->assertNotFound();

    expect($otherLead->fresh()->status)->toBe(LeadStatus::New);
});

test('deleting a lead removes its website, photos, and other records', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'name' => 'Handyman Ram',
        'logo_path' => 'teams/1/leads/9/logo.png',
        'photo_paths' => ['teams/1/leads/9/photo.jpg'],
    ]);
    Storage::disk('public')->put('teams/1/leads/9/logo.png', 'logo');
    Storage::disk('public')->put('teams/1/leads/9/photo.jpg', 'photo');
    Storage::disk('public')->put('teams/1/leads/9/hero.png', 'hero');
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'images' => ['home_hero' => 'teams/1/leads/9/hero.png'],
        ],
    ]);
    $proposal = Proposal::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->from(route('leads.edit', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->delete(route('leads.destroy', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertRedirect(route('leads.index', $user->currentTeam));

    expect(Lead::query()->find($lead->id))->toBeNull()
        ->and(Site::query()->find($site->id))->toBeNull()
        ->and(Proposal::query()->find($proposal->id))->toBeNull()
        ->and(Storage::disk('public')->exists('teams/1/leads/9/logo.png'))->toBeFalse()
        ->and(Storage::disk('public')->exists('teams/1/leads/9/photo.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('teams/1/leads/9/hero.png'))->toBeFalse();
});

test('a lead from another team cannot be deleted', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)
        ->delete(route('leads.destroy', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertNotFound();

    expect($lead->fresh())->not->toBeNull();
});

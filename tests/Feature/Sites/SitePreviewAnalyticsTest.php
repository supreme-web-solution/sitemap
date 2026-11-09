<?php

use App\Enums\LeadActivityType;
use App\Enums\TeamRole;
use App\Models\Lead;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;

test('team members can open preview analytics for a site', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $lead = Lead::factory()->for($team)->create(['name' => 'Acme Plumbing']);
    $site = Site::factory()->create([
        'team_id' => $team->id,
        'lead_id' => $lead->id,
        'views_count' => 12,
        'preview_section_views' => ['hero' => 10, 'services' => 4],
    ]);

    $lead->recordActivity(LeadActivityType::PreviewClaim, 'Claimed', [
        'name' => 'Sam',
        'contact' => 'sam@example.com',
        'intent' => 'claim',
    ]);

    $this->actingAs($user)
        ->get(route('sites.analytics', [
            'current_team' => $team->slug,
            'site' => $site->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sites/Analytics')
            ->where('site.name', 'Acme Plumbing')
            ->where('site.viewsCount', 12)
            ->where('site.claimCount', 1)
            ->has('sectionViews')
            ->has('interestEvents', 1));
});

test('users cannot view analytics for another team site', function () {
    $user = User::factory()->create();
    $other = Team::factory()->create();
    $owner = User::factory()->create();
    $other->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $lead = Lead::factory()->for($other)->create();
    $site = Site::factory()->create(['team_id' => $other->id, 'lead_id' => $lead->id]);

    $this->actingAs($user)
        ->get(route('sites.analytics', [
            'current_team' => $user->currentTeam->slug,
            'site' => $site->id,
        ]))
        ->assertNotFound();
});

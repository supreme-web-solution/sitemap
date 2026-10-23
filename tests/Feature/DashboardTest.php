<?php

use App\Enums\LeadStatus;
use App\Enums\TeamRole;
use App\Models\Lead;
use App\Models\Site;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('users do not need to verify their email to use the app', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk();
});

test('dashboard includes pending invitations for the authenticated user', function () {
    $owner = User::factory()->create(['name' => 'Taylor Otwell']);
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create(['name' => 'Laravel Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 1)
        ->where('pendingInvitations.0.code', $invitation->code)
        ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
        ->where('pendingInvitations.0.team.name', 'Laravel Team')
        ->where('pendingInvitations.0.team.slug', $team->slug)
        ->missing('pendingInvitations.0.teamName'),
    );
});

test('dashboard does not include accepted invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    TeamInvitation::factory()->accepted()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );
});

test('dashboard excludes expired invitations without deleting them', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'invited@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard does not include or delete other users invitations', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $invitation = TeamInvitation::factory()->expired()->create([
        'team_id' => $team->id,
        'email' => 'someone@example.com',
        'invited_by' => $owner->id,
    ]);

    $response = $this
        ->actingAs($invitedUser)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('pendingInvitations', 0),
    );

    $this->assertDatabaseHas('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('dashboard exposes pipeline counts, a 30 day chart and recent activity for the current team only', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Lead::factory()->count(2)->for($team)->create();
    Lead::factory()->for($team)->create(['status' => LeadStatus::Won, 'deal_value' => 500]);

    $site = Site::factory()->create([
        'lead_id' => Lead::factory()->for($team)->create(['name' => 'Acme Plumbing'])->id,
        'views_count' => 3,
        'last_viewed_at' => now(),
    ]);

    $oldSite = Site::factory()->create([
        'lead_id' => Lead::factory()->for($team)->create()->id,
        'created_at' => now()->subDays(40),
    ]);

    Lead::factory()->count(5)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.leads', 5)
            ->where('stats.sites', 2)
            ->where('stats.revenue', 500)
            ->where('pipeline.new', 4)
            ->where('pipeline.won', 1)
            ->where('pipeline.lost', 0)
            ->has('chart.days', 30)
            ->has('chart.leads', 30)
            ->has('chart.sites', 30)
            ->where('chart.sites.29', 1)
            ->where('chart.leads.29', 5)
            ->where('activity.0.title', fn ($title) => is_string($title))
            ->has('activity', 6)
            ->where('recentSites.0.previewUrl', fn ($url) => str_contains($url, $site->slug) || str_contains($url, $oldSite->slug)),
        );
});

test('dashboard activity is sorted from newest to oldest', function () {
    $user = User::factory()->create();

    Lead::factory()->for($user->currentTeam)->create(['name' => 'Older Lead', 'created_at' => now()->subDays(3)]);
    Lead::factory()->for($user->currentTeam)->create(['name' => 'Newer Lead', 'created_at' => now()->subHour()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.0.title', 'Newer Lead')
            ->where('activity.1.title', 'Older Lead'),
        );
});

test('dashboard lists open follow-ups soonest first and counts the ones that are due', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Lead::factory()->for($team)->create(['name' => 'Later', 'next_follow_up_at' => today()->addDays(5)]);
    Lead::factory()->for($team)->create(['name' => 'Overdue', 'next_follow_up_at' => today()->subDays(2)]);
    Lead::factory()->for($team)->create(['name' => 'Today', 'next_follow_up_at' => today()]);
    Lead::factory()->for($team)->create(['name' => 'Closed', 'status' => LeadStatus::Won, 'next_follow_up_at' => today()->subDay()]);
    Lead::factory()->create(['name' => 'Other team', 'next_follow_up_at' => today()->subDay()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('followUps.dueCount', 2)
            ->has('followUps.items', 3)
            ->where('followUps.items.0.name', 'Overdue')
            ->where('followUps.items.0.isOverdue', true)
            ->where('followUps.items.1.name', 'Today')
            ->where('followUps.items.1.isDueToday', true)
            ->where('followUps.items.2.name', 'Later'),
        );
});

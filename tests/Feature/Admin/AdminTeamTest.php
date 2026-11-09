<?php

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    config(['admin.emails' => ['boss@example.com']]);
    $this->admin = User::factory()->create(['email' => 'boss@example.com']);
});

test('an admin can fetch team members on demand', function () {
    $owner = User::factory()->create(['name' => 'Owner Person', 'email' => 'owner@example.com']);
    $team = $owner->personalTeam();

    $this->actingAs($this->admin)
        ->getJson(route('admin.teams.members', $team))
        ->assertOk()
        ->assertJsonPath('team.slug', $team->slug)
        ->assertJsonPath('members.0.email', 'owner@example.com')
        ->assertJsonPath('members.0.role', 'owner');
});

test('an admin can delete a team and members with no other teams', function () {
    $owner = User::factory()->create();
    $team = $owner->personalTeam();

    $this->actingAs($this->admin)
        ->delete(route('admin.teams.destroy', $team))
        ->assertRedirect();

    expect(Team::whereKey($team->id)->exists())->toBeFalse()
        ->and(User::whereKey($owner->id)->exists())->toBeFalse();
});

test('deleting a team keeps members who still belong to another team', function () {
    $owner = User::factory()->create();
    $personal = $owner->personalTeam();
    $extra = Team::factory()->create();
    $extra->members()->attach($owner, ['role' => 'owner']);

    $this->actingAs($this->admin)
        ->delete(route('admin.teams.destroy', $extra))
        ->assertRedirect();

    expect(Team::whereKey($extra->id)->exists())->toBeFalse()
        ->and(User::whereKey($owner->id)->exists())->toBeTrue()
        ->and($owner->fresh()->belongsToTeam($personal))->toBeTrue();
});

test('an admin can assign a plan from the teams page', function () {
    $plan = Plan::factory()->create(['name' => 'Agency']);
    $team = User::factory()->create()->personalTeam();

    $this->actingAs($this->admin)
        ->put(route('admin.teams.update', $team), ['plan_id' => $plan->id])
        ->assertRedirect();

    expect($team->refresh()->plan_id)->toBe($plan->id);
});

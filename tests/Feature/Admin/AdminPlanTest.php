<?php

use App\Models\Plan;
use App\Models\UsageRecord;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['admin.emails' => ['boss@example.com']]);
    $this->admin = User::factory()->create(['email' => 'boss@example.com']);
});

test('the plans page includes teams and analytics for each plan', function () {
    $plan = Plan::factory()->create(['name' => 'Agency', 'slug' => 'agency']);
    $team = User::factory()->create()->personalTeam();
    $team->forceFill(['plan_id' => $plan->id])->save();

    UsageRecord::query()->create([
        'team_id' => $team->id,
        'metric' => 'lead_searches',
        'period' => now()->format('Y-m'),
        'count' => 7,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.plans.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plans/Index')
            ->has('plans', 1)
            ->where('plans.0.name', 'Agency')
            ->where('plans.0.teams_count', 1)
            ->where('plans.0.teams.0.slug', $team->slug)
            ->where('plans.0.analytics.teams_count', 1)
            ->where('plans.0.analytics.usage_this_month', fn ($rows) => collect($rows)
                ->contains(fn ($row) => $row['key'] === 'lead_searches' && $row['used'] === 7)));
});

test('guests and non-admins cannot open plans', function () {
    $member = User::factory()->create();

    $this->get(route('admin.plans.index'))->assertRedirect(route('login'));
    $this->actingAs($member)->get(route('admin.plans.index'))->assertForbidden();
});

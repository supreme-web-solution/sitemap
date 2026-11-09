<?php

use App\Enums\TeamRole;
use App\Models\Plan;
use App\Models\User;

test('owners can switch to another free plan themselves', function () {
    $starter = Plan::factory()->create([
        'name' => 'Starter',
        'slug' => 'starter-self',
        'price_cents' => 0,
        'is_active' => true,
        'is_default' => true,
    ]);
    $pro = Plan::factory()->create([
        'name' => 'Pro',
        'slug' => 'pro-self',
        'price_cents' => 0,
        'is_active' => true,
        'is_default' => false,
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->forceFill(['plan_id' => $starter->id])->save();

    $this->actingAs($user)
        ->post(route('plan.change', [
            'current_team' => $team->slug,
            'plan' => $pro->id,
        ]))
        ->assertRedirect(route('plan.index', ['current_team' => $team->slug]));

    expect($team->fresh()->plan_id)->toBe($pro->id);
});

test('paid plans without stripe checkout cannot be self-selected', function () {
    $free = Plan::factory()->create([
        'slug' => 'free-self',
        'price_cents' => 0,
        'is_active' => true,
        'is_default' => true,
    ]);
    $paid = Plan::factory()->create([
        'slug' => 'paid-self',
        'price_cents' => 4900,
        'stripe_price_id' => null,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->forceFill(['plan_id' => $free->id])->save();

    $this->actingAs($user)
        ->from(route('plan.index', ['current_team' => $team->slug]))
        ->post(route('plan.change', [
            'current_team' => $team->slug,
            'plan' => $paid->id,
        ]))
        ->assertRedirect(route('plan.index', ['current_team' => $team->slug]));

    expect($team->fresh()->plan_id)->toBe($free->id);
});

test('members cannot change the team plan', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->forceFill(['current_team_id' => $team->id])->save();

    $target = Plan::factory()->create([
        'slug' => 'agency-self',
        'price_cents' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($member)
        ->post(route('plan.change', [
            'current_team' => $team->slug,
            'plan' => $target->id,
        ]))
        ->assertForbidden();
});

test('the plan page lists available plans for self-serve', function () {
    Plan::factory()->create([
        'slug' => 'listed-self',
        'name' => 'Listed Plan',
        'price_cents' => 0,
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('plan.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('plan/Index')
            ->has('plans')
            ->where('canChangePlan', true));
});

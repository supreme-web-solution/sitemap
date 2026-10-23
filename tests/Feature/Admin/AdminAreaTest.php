<?php

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['admin.emails' => ['boss@example.com']]);
    $this->admin = User::factory()->create(['email' => 'boss@example.com']);
});

test('only administrators listed in ADMIN_EMAILS can open the admin area', function () {
    $member = User::factory()->create();

    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->actingAs($member)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
});

test('admin emails are matched ignoring case and surrounding spaces', function () {
    putenv('ADMIN_EMAILS= Boss@Example.com , second@example.com ');
    $config = require config_path('admin.php');
    putenv('ADMIN_EMAILS');

    expect($config['emails'])->toBe(['boss@example.com', 'second@example.com']);
});

test('the admin flag is shared with the browser', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', true));

    $member = User::factory()->create();

    $this->actingAs($member)->get(route('dashboard', ['current_team' => $member->currentTeam->slug]))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isAdmin', false));
});

test('an admin can create a user with a personal team and a plan', function () {
    $plan = Plan::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Casey Client',
        'email' => 'casey@example.com',
        'password' => 'Str0ng-Passw0rd!',
        'verified' => true,
        'plan_id' => $plan->id,
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'casey@example.com')->sole();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->personalTeam()->plan_id)->toBe($plan->id)
        ->and($user->current_team_id)->toBe($user->personalTeam()->id);
});

test('an admin can update a user and reset their password', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'password' => 'An0ther-Str0ng-One!',
        'verified' => false,
    ])->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('New Name')
        ->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('An0ther-Str0ng-One!', $user->password))->toBeTrue();
});

test('leaving the password blank keeps the existing one', function () {
    $user = User::factory()->create();
    $hash = $user->password;

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => $user->name, 'email' => $user->email, 'verified' => true,
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->password)->toBe($hash);
});

test('a suspended user is signed out and cannot sign back in', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.users.suspend', $user))->assertRedirect();
    expect($user->refresh()->isSuspended())->toBeTrue();

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->actingAs($this->admin)->delete(route('admin.users.restore', $user));
    expect($user->refresh()->isSuspended())->toBeFalse();
});

test('administrators and yourself cannot be suspended or deleted', function () {
    $other = User::factory()->create(['email' => 'second@example.com']);
    config(['admin.emails' => ['boss@example.com', 'second@example.com']]);

    $this->actingAs($this->admin)->post(route('admin.users.suspend', $this->admin));
    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $other));

    expect($this->admin->refresh()->isSuspended())->toBeFalse()
        ->and(User::whereKey($other->id)->exists())->toBeTrue();
});

test('deleting a user removes teams only they belonged to', function () {
    $user = User::factory()->create();
    $team = $user->personalTeam();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));

    expect(User::whereKey($user->id)->exists())->toBeFalse()
        ->and(Team::whereKey($team->id)->exists())->toBeFalse();
});

test('an admin can search and filter users', function () {
    User::factory()->create(['name' => 'Zed Findable']);
    User::factory()->create(['name' => 'Someone Else', 'suspended_at' => now()]);

    $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => 'Findable']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Zed Findable'));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'suspended']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Someone Else'));
});

test('an admin can impersonate a user and return to their own account', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.impersonate', $user))
        ->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));

    $this->assertAuthenticatedAs($user);
    $this->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('impersonating.name', $this->admin->name)
            ->where('auth.isAdmin', false));
    $this->get(route('admin.dashboard'))->assertForbidden();

    $this->post(route('impersonation.stop'))->assertRedirect(route('admin.users.index'));

    $this->assertAuthenticatedAs($this->admin);
});

test('a suspended user can still be inspected by impersonating them', function () {
    $user = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($this->admin)->post(route('admin.users.impersonate', $user));

    $this->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))->assertOk();
});

test('other administrators cannot be impersonated', function () {
    $other = User::factory()->create(['email' => 'second@example.com']);
    config(['admin.emails' => ['boss@example.com', 'second@example.com']]);

    $this->actingAs($this->admin)->post(route('admin.users.impersonate', $other));

    $this->assertAuthenticatedAs($this->admin);
});

test('regular users cannot impersonate', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('admin.users.impersonate', User::factory()->create()))->assertForbidden();
});

test('an admin can assign a plan to a team', function () {
    $plan = Plan::factory()->create();
    $team = User::factory()->create()->personalTeam();

    $this->actingAs($this->admin)->put(route('admin.teams.update', $team), ['plan_id' => $plan->id]);
    expect($team->refresh()->plan_id)->toBe($plan->id);

    $this->actingAs($this->admin)->put(route('admin.teams.update', $team), ['plan_id' => null]);
    expect($team->refresh()->plan_id)->toBeNull();
});

test('an admin can create, update and delete plans', function () {
    $payload = [
        'name' => 'Pro', 'slug' => 'pro', 'description' => 'For growing agencies', 'price' => 49.5,
        'is_default' => false, 'is_active' => true,
        'limits' => ['lead_searches' => 20, 'site_generations' => '', 'bogus' => 5],
        'features' => ['white_label', 'messaging'],
    ];

    $this->actingAs($this->admin)->post(route('admin.plans.store'), $payload)->assertSessionHasNoErrors();

    $plan = Plan::where('slug', 'pro')->sole();

    expect($plan->price_cents)->toBe(4950)
        ->and($plan->limit(App\Enums\UsageMetric::LeadSearches))->toBe(20)
        ->and($plan->limit(App\Enums\UsageMetric::SiteGenerations))->toBeNull()
        ->and($plan->limits)->not->toHaveKey('bogus')
        ->and($plan->allows(App\Enums\PlanFeature::WhiteLabel))->toBeTrue()
        ->and($plan->allows(App\Enums\PlanFeature::AiEditing))->toBeFalse();

    $this->actingAs($this->admin)->put(route('admin.plans.update', $plan), [...$payload, 'name' => 'Pro Plus'])->assertSessionHasNoErrors();
    expect($plan->refresh()->name)->toBe('Pro Plus');

    $this->actingAs($this->admin)->delete(route('admin.plans.destroy', $plan));
    expect(Plan::count())->toBe(0);
});

test('only one plan can be the default', function () {
    $first = Plan::factory()->default()->create();

    $this->actingAs($this->admin)->post(route('admin.plans.store'), [
        'name' => 'Second', 'slug' => 'second', 'price' => 0, 'is_default' => true, 'limits' => [], 'features' => [],
    ])->assertSessionHasNoErrors();

    expect($first->refresh()->is_default)->toBeFalse()
        ->and(Plan::default()->slug)->toBe('second');
});

test('a plan needs a unique slug and a valid feature', function () {
    Plan::factory()->create(['slug' => 'taken']);

    $this->actingAs($this->admin)->post(route('admin.plans.store'), [
        'name' => 'X', 'slug' => 'taken', 'price' => 0, 'features' => ['nonsense'],
    ])->assertSessionHasErrors(['slug', 'features.0']);
});
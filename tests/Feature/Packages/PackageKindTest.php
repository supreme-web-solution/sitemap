<?php

use App\Enums\BillingInterval;
use App\Enums\PackageKind;
use App\Enums\ProposalStatus;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Proposal;
use App\Models\RecurringInvoice;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('packages store a kind and default to service when omitted', function () {
    $user = User::factory()->create();
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)
        ->post(route('packages.store', ['current_team' => $slug]), [
            'name' => 'Retainer',
            'price' => 100,
            'interval' => 'monthly',
        ])
        ->assertSessionHasNoErrors();

    expect(Package::sole()->kind)->toBe(PackageKind::Service);

    $this->actingAs($user)
        ->post(route('packages.store', ['current_team' => $slug]), [
            'name' => 'Hosting',
            'kind' => 'hosting',
            'price' => 15,
            'interval' => 'monthly',
        ])
        ->assertSessionHasNoErrors();

    expect(Package::query()->where('name', 'Hosting')->sole()->kind)->toBe(PackageKind::Hosting);
});

test('an empty catalog can seed website hosting and maintenance defaults', function () {
    $user = User::factory()->create();
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)
        ->get(route('packages.index', ['current_team' => $slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('packages/Index')
            ->where('canSeedDefaults', true)
            ->has('kinds', 4));

    $this->actingAs($user)
        ->post(route('packages.seed-defaults', ['current_team' => $slug]))
        ->assertRedirect();

    $packages = Package::query()->orderBy('name')->get();

    expect($packages)->toHaveCount(3)
        ->and($packages->pluck('name')->all())->toBe(['Hosting', 'Maintenance', 'Website build'])
        ->and($packages->firstWhere('name', 'Website build')->kind)->toBe(PackageKind::OneTime)
        ->and($packages->firstWhere('name', 'Website build')->interval)->toBeNull()
        ->and($packages->firstWhere('name', 'Hosting')->kind)->toBe(PackageKind::Hosting)
        ->and($packages->firstWhere('name', 'Hosting')->interval)->toBe(BillingInterval::Monthly)
        ->and($packages->firstWhere('name', 'Maintenance')->kind)->toBe(PackageKind::Maintenance)
        ->and($packages->firstWhere('name', 'Maintenance')->interval)->toBe(BillingInterval::Monthly);

    $this->actingAs($user)
        ->post(route('packages.seed-defaults', ['current_team' => $slug]))
        ->assertRedirect();

    expect(Package::count())->toBe(3);
});

test('accepting a proposal links recurring invoices to the lead site when one exists', function () {
    $lead = Lead::factory()->create(['email' => 'client@example.com']);
    $site = Site::factory()->create(['lead_id' => $lead->id, 'team_id' => $lead->team_id]);
    $proposal = Proposal::factory()->withDefaultItems()->create([
        'lead_id' => $lead->id,
        'status' => ProposalStatus::Sent,
    ])->load('items');

    $this->post(route('proposals.public.accept', $proposal->public_token), [
        'name' => 'Pat Client',
        'agree' => '1',
    ])->assertRedirect();

    expect(RecurringInvoice::sole()->site_id)->toBe($site->id);
});

test('a recurring schedule can be linked and unlinked from a site', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id, 'team_id' => $user->currentTeam->id]);
    $schedule = RecurringInvoice::factory()->create([
        'lead_id' => $lead->id,
        'team_id' => $user->currentTeam->id,
        'site_id' => null,
    ]);
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $slug, 'site' => $site->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Edit')
            ->has('clientServices.attachable', 1)
            ->has('clientServices.linked', 0));

    $this->actingAs($user)
        ->put(route('recurring-invoices.site', [
            'current_team' => $slug,
            'recurringInvoice' => $schedule->id,
        ]), ['site_id' => $site->id])
        ->assertRedirect();

    expect($schedule->refresh()->site_id)->toBe($site->id);

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $slug, 'site' => $site->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('clientServices.linked', 1)
            ->has('clientServices.attachable', 0));

    $this->actingAs($user)
        ->put(route('recurring-invoices.site', [
            'current_team' => $slug,
            'recurringInvoice' => $schedule->id,
        ]), ['site_id' => null])
        ->assertRedirect();

    expect($schedule->refresh()->site_id)->toBeNull();
});

test('a schedule cannot be linked to another leads site', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $otherLead = Lead::factory()->for($user->currentTeam)->create();
    $otherSite = Site::factory()->create(['lead_id' => $otherLead->id, 'team_id' => $user->currentTeam->id]);
    $schedule = RecurringInvoice::factory()->create([
        'lead_id' => $lead->id,
        'team_id' => $user->currentTeam->id,
    ]);

    $this->actingAs($user)
        ->put(route('recurring-invoices.site', [
            'current_team' => $user->currentTeam->slug,
            'recurringInvoice' => $schedule->id,
        ]), ['site_id' => $otherSite->id])
        ->assertSessionHasErrors('site_id');

    expect($schedule->refresh()->site_id)->toBeNull();
});

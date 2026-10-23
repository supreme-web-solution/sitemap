<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Enums\TeamRole;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Proposal;
use App\Models\Site;
use App\Models\User;

function portalLead(?User $user = null, array $leadAttributes = []): array
{
    $user ??= User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create($leadAttributes);

    return [$user, $user->currentTeam, $lead];
}

test('a team member can create, replace and turn off a lead portal link', function () {
    [$user, $team, $lead] = portalLead();
    $routeArgs = ['current_team' => $team->slug, 'lead' => $lead];

    $this->actingAs($user)->post(route('leads.portal.store', $routeArgs))->assertRedirect();
    $first = $lead->refresh()->portal_token;

    expect($first)->toHaveLength(40)->and($lead->portalUrl())->toBe(route('portal.show', $first));

    $this->actingAs($user)->post(route('leads.portal.store', $routeArgs));
    expect($lead->refresh()->portal_token)->not->toBe($first);

    $this->actingAs($user)->delete(route('leads.portal.destroy', $routeArgs));
    expect($lead->refresh()->portal_token)->toBeNull();
    $this->get(route('portal.show', $first))->assertNotFound();
});

test('the portal cannot be switched on when the plan does not include it', function () {
    [$user, $team, $lead] = portalLead();
    $team->forceFill(['plan_id' => Plan::factory()->create(['features' => []])->id])->save();

    $this->actingAs($user)->post(route('leads.portal.store', ['current_team' => $team->slug, 'lead' => $lead]))->assertRedirect();

    expect($lead->refresh()->portal_token)->toBeNull();
});

test('a portal link stops working if the plan loses the feature', function () {
    [, $team, $lead] = portalLead(leadAttributes: ['portal_token' => 'tok-abc']);
    $this->get(route('portal.show', 'tok-abc'))->assertOk();

    $team->forceFill(['plan_id' => Plan::factory()->create(['features' => []])->id])->save();

    $this->get(route('portal.show', 'tok-abc'))->assertNotFound();
});

test('the portal lists the website, sent invoices and proposals but not drafts', function () {
    [, $team, $lead] = portalLead(leadAttributes: ['portal_token' => 'tok-abc']);
    Site::factory()->for($lead)->create(['team_id' => $team->id]);
    $open = Invoice::factory()->create(['lead_id' => $lead->id, 'number' => 'INV-0001']);
    Invoice::factory()->create(['lead_id' => $lead->id, 'number' => 'INV-0002', 'status' => InvoiceStatus::Draft, 'sent_at' => null]);
    Invoice::factory()->create(['lead_id' => $lead->id, 'number' => 'INV-0003', 'status' => InvoiceStatus::Void]);
    Proposal::factory()->create(['lead_id' => $lead->id, 'title' => 'Visible proposal']);
    Proposal::factory()->create(['lead_id' => $lead->id, 'title' => 'Secret draft', 'status' => App\Enums\ProposalStatus::Draft]);

    $this->get(route('portal.show', 'tok-abc'))
        ->assertOk()
        ->assertSee('Your website')
        ->assertSee('INV-0001')
        ->assertDontSee('INV-0002')
        ->assertDontSee('INV-0003')
        ->assertSee($open->payUrl(), false)
        ->assertSee('Visible proposal')
        ->assertDontSee('Secret draft');
});

test('the portal uses the agency brand and shows powered by only without white label', function () {
    [, $team] = portalLead(leadAttributes: ['portal_token' => 'tok-abc']);
    $team->forceFill(['brand_name' => 'Bright Agency', 'brand_color' => '#ff6600'])->save();
    $team->forceFill(['plan_id' => Plan::factory()->create(['features' => ['client_portal']])->id])->save();

    $this->get(route('portal.show', 'tok-abc'))
        ->assertSee('Bright Agency')
        ->assertSee('#ff6600', false)
        ->assertSee('Powered by');

    $team->forceFill(['plan_id' => Plan::factory()->withAllFeatures()->create()->id])->save();

    $this->get(route('portal.show', 'tok-abc'))->assertDontSee('Powered by');
});

test('the client can approve the website once, and it is logged', function () {
    [, $team, $lead] = portalLead(leadAttributes: ['portal_token' => 'tok-abc']);
    $site = Site::factory()->for($lead)->create(['team_id' => $team->id]);

    $this->post(route('portal.approve', 'tok-abc'))->assertRedirect();
    $approvedAt = $site->refresh()->approved_at;

    $this->post(route('portal.approve', 'tok-abc'));

    expect($approvedAt)->not->toBeNull()
        ->and($site->refresh()->approved_at->equalTo($approvedAt))->toBeTrue()
        ->and($lead->activities()->where('type', LeadActivityType::SiteApproved)->count())->toBe(1);
});

test('client feedback lands on the timeline and schedules a follow-up for today', function () {
    [, , $lead] = portalLead(leadAttributes: ['portal_token' => 'tok-abc']);

    $this->post(route('portal.feedback', 'tok-abc'), ['message' => 'Please make the logo bigger.'])->assertSessionHasNoErrors();

    $activity = $lead->activities()->where('type', LeadActivityType::ClientFeedback)->sole();

    expect($activity->summary)->toBe('Please make the logo bigger.')
        ->and($lead->refresh()->next_follow_up_at->isToday())->toBeTrue();

    $this->post(route('portal.feedback', 'tok-abc'), ['message' => ''])->assertSessionHasErrors('message');
});

test('branding can be saved by team admins but not plain members', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $payload = ['name' => 'Bright Agency', 'logo_url' => 'https://example.com/logo.png', 'color' => '#112233', 'email' => 'hello@bright.test'];

    $this->actingAs($member)->put(route('branding.update', ['current_team' => $team->slug]), $payload)->assertForbidden();

    $this->actingAs($owner)->put(route('branding.update', ['current_team' => $team->slug]), $payload)->assertSessionHasNoErrors();

    expect($team->refresh()->brand())->toMatchArray(['name' => 'Bright Agency', 'logo' => 'https://example.com/logo.png', 'color' => '#112233', 'email' => 'hello@bright.test']);
});

test('branding rejects a non-https logo and a malformed colour', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->put(route('branding.update', ['current_team' => $owner->currentTeam->slug]), ['logo_url' => 'http://example.com/x.png', 'color' => 'red'])
        ->assertSessionHasErrors(['logo_url', 'color']);
});

test('the invoice page shows the agency brand footer rule', function () {
    $invoice = Invoice::factory()->create();
    $invoice->team->forceFill(['plan_id' => Plan::factory()->create(['features' => []])->id])->save();

    $this->get($invoice->payUrl())->assertSee('Powered by');

    $invoice->team->forceFill(['plan_id' => Plan::factory()->withAllFeatures()->create()->id])->save();

    $this->get($invoice->payUrl())->assertDontSee('Powered by');
});

test('the plan and usage page shows the team plan and usage', function () {
    $user = User::factory()->create();
    $user->currentTeam->forceFill(['plan_id' => Plan::factory()->create(['name' => 'Starter', 'limits' => ['lead_searches' => 5]])->id])->save();

    $this->actingAs($user)->get(route('plan.index', ['current_team' => $user->currentTeam->slug]))
        ->assertInertia(fn ($page) => $page
            ->component('plan/Index')
            ->where('plan.name', 'Starter')
            ->where('usage.0.key', 'lead_searches')
            ->where('usage.0.limit', 5));
});
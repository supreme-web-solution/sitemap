<?php

use App\Actions\Leads\StoreLeadsFromListings;
use App\Data\BusinessListing;
use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use App\Exceptions\PlanLimitException;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use App\Services\Billing\UsageMeter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

function teamOnPlan(array $limits = [], array $features = []): array
{
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['limits' => $limits, 'features' => $features]);
    $user->currentTeam->forceFill(['plan_id' => $plan->id])->save();

    return [$user, $user->currentTeam];
}

test('a team with no plan and no default plan is unlimited', function () {
    $user = User::factory()->create();
    $usage = app(UsageMeter::class);

    expect($usage->limit($user->currentTeam, UsageMetric::LeadSearches))->toBeNull()
        ->and($usage->allows($user->currentTeam, PlanFeature::Messaging))->toBeTrue();

    $usage->consume($user->currentTeam, UsageMetric::LeadSearches, 500);
});

test('teams without their own plan get the default plan', function () {
    Plan::factory()->default()->create(['limits' => ['lead_searches' => 1]]);
    $team = User::factory()->create()->currentTeam;

    expect(app(UsageMeter::class)->limit($team, UsageMetric::LeadSearches))->toBe(1);
});

test('monthly usage is counted and stops at the cap', function () {
    [, $team] = teamOnPlan(['lead_searches' => 2]);
    $usage = app(UsageMeter::class);

    $usage->consume($team, UsageMetric::LeadSearches);
    $usage->consume($team, UsageMetric::LeadSearches);

    expect($usage->used($team, UsageMetric::LeadSearches))->toBe(2)
        ->and($usage->remaining($team, UsageMetric::LeadSearches))->toBe(0)
        ->and(fn () => $usage->consume($team, UsageMetric::LeadSearches))->toThrow(PlanLimitException::class);
});

test('monthly usage starts again each month', function () {
    [, $team] = teamOnPlan(['lead_searches' => 1]);
    $usage = app(UsageMeter::class);

    $this->travelTo(now()->startOfMonth()->addDays(3));
    $usage->consume($team, UsageMetric::LeadSearches);

    $this->travelTo(now()->addMonth());
    $usage->consume($team, UsageMetric::LeadSearches);

    expect($usage->used($team, UsageMetric::LeadSearches))->toBe(1);
});

test('a zero cap blocks the action entirely', function () {
    [, $team] = teamOnPlan(['audits' => 0]);

    expect(fn () => app(UsageMeter::class)->consume($team, UsageMetric::Audits))->toThrow(PlanLimitException::class);
});

test('usage is counted per team', function () {
    [, $team] = teamOnPlan(['lead_searches' => 1]);
    $other = User::factory()->create()->currentTeam;
    $other->forceFill(['plan_id' => $team->plan_id])->save();

    app(UsageMeter::class)->consume($team, UsageMetric::LeadSearches);

    app(UsageMeter::class)->consume($other, UsageMetric::LeadSearches);
    expect(app(UsageMeter::class)->used($other, UsageMetric::LeadSearches))->toBe(1);
});

test('features locked by the plan are refused', function () {
    [, $team] = teamOnPlan([], ['messaging']);
    $usage = app(UsageMeter::class);

    expect($usage->allows($team, PlanFeature::Messaging))->toBeTrue()
        ->and($usage->allows($team, PlanFeature::WhiteLabel))->toBeFalse()
        ->and(fn () => $usage->ensureFeature($team, PlanFeature::WhiteLabel))->toThrow(PlanLimitException::class);
});

test('starting a lead search past the cap redirects back with a message and does not queue work', function () {
    Bus::fake();
    [$user, $team] = teamOnPlan(['lead_searches' => 1]);
    $payload = ['keyword' => 'plumbers', 'location' => 'Austin, TX', 'website_filter' => App\Enums\WebsiteFilter::AllPlaces->value, 'max_results' => 20];

    $this->actingAs($user)->post(route('lead-searches.store', ['current_team' => $team->slug]), $payload)->assertRedirect();
    $this->actingAs($user)->from('/back')->post(route('lead-searches.store', ['current_team' => $team->slug]), $payload)->assertRedirect('/back');

    expect(LeadSearch::count())->toBe(1);
    Bus::assertDispatchedTimes(App\Jobs\RunLeadSearch::class, 1);
});

test('generating a site past the cap is refused', function () {
    Bus::fake();
    [$user, $team] = teamOnPlan(['site_generations' => 0]);
    $lead = Lead::factory()->for($team)->create();

    $this->actingAs($user)->post(route('leads.site.store', ['current_team' => $team->slug, 'lead' => $lead]))->assertRedirect();

    Bus::assertNothingDispatched();
});

test('a new website is refused when the team already holds its allowed number', function () {
    Bus::fake();
    [$user, $team] = teamOnPlan(['sites' => 1]);
    Site::factory()->for(Lead::factory()->for($team))->create(['team_id' => $team->id]);
    $lead = Lead::factory()->for($team)->create();

    $this->actingAs($user)->post(route('leads.site.store', ['current_team' => $team->slug, 'lead' => $lead]));

    Bus::assertNothingDispatched();
});

test('regenerating an existing website is not blocked by the website total', function () {
    Bus::fake();
    [$user, $team] = teamOnPlan(['sites' => 1]);
    $site = Site::factory()->for(Lead::factory()->for($team))->create(['team_id' => $team->id]);

    $this->actingAs($user)->post(route('leads.site.store', ['current_team' => $team->slug, 'lead' => $site->lead_id]));

    Bus::assertDispatched(App\Jobs\GenerateLeadSite::class);
});

test('outreach emails are counted and capped but logged contacts are free', function () {
    Mail::fake();
    [$user, $team] = teamOnPlan(['emails' => 1]);
    $lead = Lead::factory()->for($team)->create(['email' => 'owner@example.com']);
    $url = route('leads.outreach.store', ['current_team' => $team->slug, 'lead' => $lead]);

    $this->actingAs($user)->post($url, ['mode' => 'send', 'subject' => 'Hi', 'body' => 'Hello'])->assertRedirect();
    $this->actingAs($user)->post($url, ['mode' => 'send', 'subject' => 'Hi again', 'body' => 'Hello'])->assertRedirect();
    $this->actingAs($user)->post($url, ['mode' => 'log', 'subject' => 'Called', 'body' => 'Spoke'])->assertRedirect();

    Mail::assertQueuedCount(1);
    expect(app(UsageMeter::class)->used($team, UsageMetric::Emails))->toBe(1);
});

test('new leads from a search are trimmed to the remaining lead allowance but existing ones refresh', function () {
    [, $team] = teamOnPlan(['leads' => 3]);
    Lead::factory()->for($team)->create(['place_id' => 'existing-1', 'name' => 'Old Name']);
    $search = LeadSearch::factory()->for($team)->create();

    $listing = fn (string $id, string $name) => new BusinessListing(placeId: $id, name: $name);

    app(StoreLeadsFromListings::class)->handle($search, [
        $listing('new-1', 'One'), $listing('existing-1', 'Fresh Name'), $listing('new-2', 'Two'), $listing('new-3', 'Three'),
    ]);

    expect($team->leads()->count())->toBe(3)
        ->and($team->leads()->where('place_id', 'existing-1')->value('name'))->toBe('Fresh Name');
});
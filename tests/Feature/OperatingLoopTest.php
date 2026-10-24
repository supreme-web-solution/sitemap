<?php

use App\Enums\LeadStatus;
use App\Enums\SiteSection;
use App\Jobs\ApplySiteEdit;
use App\Jobs\CrawlSiteLinks;
use App\Jobs\RescoreTeamLeads;
use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteChatMessage;
use App\Models\User;
use App\Services\Audit\SafeHttpFetcher;
use App\Services\SiteBuilder\DeliveryHeuristics;
use App\Services\SiteBuilder\SiteRenderer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('score weights persist on the team and rescore its leads', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $lead = Lead::factory()->for($team)->create(['website' => null]);
    $before = $lead->opportunity_score;

    $this->actingAs($user)
        ->put(route('ideal-customer.update', ['current_team' => $team->slug]), [
            'categories' => ['plumber'],
            'cities' => ['Austin'],
            'minimumRating' => 4,
            'websiteFilter' => 'withoutWebsite',
            'score_weights' => [
                'website_need' => 100,
                'website_quality' => 0,
                'business_activity' => 0,
                'contactability' => 0,
                'profile_fit' => 0,
            ],
        ])
        ->assertRedirect();

    expect($team->fresh()->score_weights['website_need'])->toBe(100)
        ->and($lead->fresh()->opportunity_score)->toBe(100)
        ->and($lead->fresh()->opportunity_score)->toBeGreaterThan($before);
});

test('score weights that do not add up to 100 are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('ideal-customer.edit', ['current_team' => $user->currentTeam->slug]))
        ->put(route('ideal-customer.update', ['current_team' => $user->currentTeam->slug]), [
            'score_weights' => [
                'website_need' => 40,
                'website_quality' => 40,
                'business_activity' => 40,
                'contactability' => 40,
                'profile_fit' => 40,
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('score_weights');
});

test('the ideal customer page includes the current score weights', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ideal-customer.edit', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ideal-customer/Edit')
            ->has('scoreWeights')
            ->has('example.breakdown')
            ->where('scoreWeights.website_need', 30));
});

test('unchanged score weights do not queue another rescore', function () {
    Queue::fake([RescoreTeamLeads::class]);

    $user = User::factory()->create();
    $weights = config('prospecting.score_weights');

    $this->actingAs($user)
        ->put(route('ideal-customer.update', ['current_team' => $user->currentTeam->slug]), [
            'score_weights' => $weights,
        ])
        ->assertRedirect();

    Queue::assertNothingPushed();
});

test('brand pull fakes http and applies the accent to the generated site', function () {
    Http::preventStrayRequests();
    Http::fake([
        '93.184.216.34/*' => Http::response(
            '<html><head><meta name="theme-color" content="#c41e3a"><meta property="og:image" content="/logo.png"></head><body></body></html>',
            200,
        ),
    ]);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['website' => 'http://93.184.216.34/']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('leads.brand.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertRedirect();

    expect($site->fresh()->content['accent'])->toBe('#c41e3a');
});

test('brand pull is not found for another team lead', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->withWebsite()->create();

    $this->actingAs($user)
        ->post(route('leads.brand.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertNotFound();
});

test('improving a section enqueues the same site edit job', function () {
    Queue::fake([ApplySiteEdit::class]);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'section' => SiteSection::Faq->value,
        ])
        ->assertRedirect();

    expect(SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_USER)->value('body'))
        ->toContain('FAQ');

    Queue::assertPushedOn('ai', ApplySiteEdit::class);
});

test('fix with ai enqueues the same site edit job', function () {
    Queue::fake([ApplySiteEdit::class]);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'fix' => 'headline',
        ])
        ->assertRedirect();

    expect(SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_USER)->value('body'))
        ->toContain('headline');

    Queue::assertPushedOn('ai', ApplySiteEdit::class);
});

test('the site editor still renders after the operating loop fields are added', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['website' => 'https://example.com']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Edit')
            ->where('site.leadWebsite', 'https://example.com')
            ->has('site.sectionViews')
            ->has('health.findings'));
});

test('the pipeline board still lists the site generated stage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('leads.board', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.1.status', LeadStatus::SiteGenerated->value)
            ->where('columns.1.label', 'Site generated'));
});

test('a public preview section beacon increments views and ignores the editor', function () {
    $site = Site::factory()->create();

    $this->post(route('sites.preview.sections', ['site' => $site->slug, 'section' => 'hero']))
        ->assertNoContent();

    expect($site->fresh()->preview_section_views['hero'])->toBe(1);

    $this->post(route('sites.preview.sections', ['site' => $site->slug, 'section' => 'hero', 'internal' => 1]))
        ->assertNoContent();

    expect($site->fresh()->preview_section_views['hero'])->toBe(1);
});

test('an unknown preview section is not found', function () {
    $site = Site::factory()->create();

    $this->post(route('sites.preview.sections', ['site' => $site->slug, 'section' => 'nope']))
        ->assertNotFound();
});

test('delivery heuristics flag missing language alt and viewport', function () {
    $findings = collect(app(DeliveryHeuristics::class)->inspect('<html><body><img src="/photo.jpg"></body></html>'))
        ->keyBy('key');

    expect($findings['lang']['status'])->toBe('warn')
        ->and($findings['viewport']['status'])->toBe('warn')
        ->and($findings['alt']['status'])->toBe('warn');
});

test('crawling a site stores delivery findings beside broken links', function () {
    Http::fake();

    $lead = Lead::factory()->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    (new CrawlSiteLinks($site))->handle(
        app(SiteRenderer::class),
        app(SafeHttpFetcher::class),
        app(DeliveryHeuristics::class),
    );

    $findings = collect($site->fresh()->delivery_findings)->pluck('key');

    expect($findings)->toContain('lang', 'viewport', 'alt', 'image-weight', 'html-size', 'scripts', 'fonts', 'lcp-image')
        ->and($site->fresh()->last_crawled_at)->not->toBeNull();
});

test('delivery heuristics report html size scripts fonts and hero image hints', function () {
    $html = <<<'HTML'
<html lang="en"><head><meta name="viewport" content="width=device-width"><link rel="preload" as="font"></head>
<body><img src="/hero.jpg" alt="Hero"><script></script></body></html>
HTML;

    $findings = collect(app(DeliveryHeuristics::class)->inspect($html))->keyBy('key');

    expect($findings['html-size']['status'])->toBe('pass')
        ->and($findings['scripts']['status'])->toBe('pass')
        ->and($findings['fonts']['status'])->toBe('pass')
        ->and($findings['lcp-image']['status'])->toBe('pass');
});

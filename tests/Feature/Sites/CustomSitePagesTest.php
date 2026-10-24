<?php

use App\Enums\SiteDesignStyle;
use App\Enums\SiteLayoutVariant;
use App\Enums\SiteTemplate;
use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDesignTemplate;
use App\Models\SiteDomain;
use App\Models\User;
use App\Services\SiteBuilder\SiteCustomPages;
use App\Services\SiteBuilder\SiteRenderer;
use App\Services\SiteBuilder\SiteUrls;

/**
 * @return array{0: Site, 1: User}
 */
function customPagesEditorSite(): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['city' => 'Austin', 'category' => 'Plumber']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    return [$site, $user];
}

test('custom pages can be added and removed from the visual editor', function () {
    [$site, $user] = customPagesEditorSite();
    $landing = SiteCustomPages::template('landing', $site->lead->name, $site->lead->city, $site->lead->category);

    $this->actingAs($user)
        ->postJson(route('sites.edits.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'revision' => $site->fresh()->revision,
            'ops' => [['op' => 'insert', 'path' => 'custom_pages', 'index' => 0, 'value' => $landing]],
        ])
        ->assertOk()
        ->assertJsonPath('document.custom_pages.0.slug', $landing['slug']);

    expect($site->fresh()->content['custom_pages'][0]['headline'])->toBe($landing['headline']);

    $this->actingAs($user)
        ->postJson(route('sites.edits.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'revision' => $site->fresh()->revision,
            'ops' => [['op' => 'remove', 'path' => 'custom_pages', 'index' => 0]],
        ])
        ->assertOk()
        ->assertJsonPath('document.custom_pages', []);
});

test('duplicate custom page slugs for the same kind are rejected', function () {
    [$site, $user] = customPagesEditorSite();
    $page = SiteCustomPages::template('landing', $site->lead->name, null, null);

    $this->actingAs($user)
        ->postJson(route('sites.edits.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'revision' => 0,
            'ops' => [
                ['op' => 'insert', 'path' => 'custom_pages', 'index' => 0, 'value' => $page],
                ['op' => 'insert', 'path' => 'custom_pages', 'index' => 1, 'value' => $page],
            ],
        ])
        ->assertUnprocessable();
});

test('custom landing and location pages render on published domains', function () {
    $lead = Lead::factory()->create(['city' => 'Austin', 'category' => 'Plumber']);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'custom_pages' => [
                [
                    'kind' => 'landing',
                    'slug' => 'spring-offer',
                    'title' => 'Spring offer',
                    'headline' => 'Spring plumbing tune-up',
                    'body' => 'Book a seasonal inspection this month.',
                ],
                [
                    'kind' => 'location',
                    'slug' => 'austin',
                    'title' => 'Austin service area',
                    'headline' => 'Plumber in Austin',
                    'body' => 'We serve Austin homeowners every day.',
                    'city' => 'Austin',
                ],
            ],
        ],
    ]);
    $site->forceFill(['published_at' => now()])->save();
    SiteDomain::factory()->verified()->for($site)->create(['hostname' => 'pros.example.com']);

    $this->get('https://pros.example.com/landing/spring-offer')
        ->assertOk()
        ->assertSee('Spring plumbing tune-up');

    $this->get('https://pros.example.com/locations/austin')
        ->assertOk()
        ->assertSee('Plumber in Austin');
});

test('industry catalog templates can apply distinct layout variants', function () {
    $template = SiteDesignTemplate::query()->create([
        'slug' => 'test-split-hero',
        'name' => 'Test Split Hero',
        'base_template' => SiteTemplate::ModernClean,
        'style' => SiteDesignStyle::Modern,
        'layout_variant' => SiteLayoutVariant::SplitHero,
        'industries' => ['testing'],
        'accent_color' => '#2563eb',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $site = Site::factory()->create([
        'design_template_id' => $template->id,
        'template' => $template->base_template,
    ]);

    $html = app(SiteRenderer::class)->render($site, urls: SiteUrls::root('https://example.com'));

    expect($html)->toContain('layout-split-hero');
});

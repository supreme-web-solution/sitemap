<?php

use App\Jobs\FetchLeadReviews;
use App\Jobs\GenerateLeadSite;
use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDesignTemplate;
use App\Models\User;
use App\Services\SiteBuilder\EnsureDesignTemplateCatalog;
use App\Services\SiteBuilder\SectionVariants;
use Database\Seeders\SiteDesignTemplateSeeder;
use Illuminate\Support\Facades\Queue;

test('the design catalog is seeded once and can be filtered by industry and style', function () {
    (new SiteDesignTemplateSeeder)->run();
    (new SiteDesignTemplateSeeder)->run();

    expect(SiteDesignTemplate::query()->count())->toBe(34);

    $plumbing = SiteDesignTemplate::query()->active()->filtered('plumbing')->ordered()->get();

    expect($plumbing)->not->toBeEmpty()
        ->and($plumbing->first()->slug)->toBe('modern-trade-services');

    $warm = SiteDesignTemplate::query()->active()->filtered(style: 'warm')->count();

    expect($warm)->toBe(6);
});

test('every catalog design has valid section variants and an accent from its theme palette', function () {
    (new SiteDesignTemplateSeeder)->run();

    SiteDesignTemplate::query()->get()->each(function (SiteDesignTemplate $design) {
        expect(SectionVariants::clean($design->variants))->toBe($design->variants)
            ->and($design->variants)->toHaveKeys(array_keys(SectionVariants::CATALOG))
            ->and($design->base_template->palette())->toContain($design->accent_color);
    });
});

test('the seeder gives existing catalog rows variants without overwriting ones already set', function () {
    (new SiteDesignTemplateSeeder)->run();

    SiteDesignTemplate::query()->where('slug', 'modern-clean-pro')->update(['variants' => null]);
    SiteDesignTemplate::query()->where('slug', 'warm-studio-local')->update(['variants' => ['hero' => 'minimal']]);

    (new SiteDesignTemplateSeeder)->run();

    expect(SiteDesignTemplate::query()->where('slug', 'modern-clean-pro')->value('variants'))->toHaveKey('hero', 'split')
        ->and(SiteDesignTemplate::query()->where('slug', 'warm-studio-local')->value('variants'))
        ->toHaveKey('hero', 'minimal')
        ->toHaveKey('footer');
});

test('a design adds its section variants as classes on the rendered site', function () {
    (new SiteDesignTemplateSeeder)->run();

    $design = SiteDesignTemplate::query()->where('slug', 'modern-home-pros')->firstOrFail();
    $site = Site::factory()->create(['design_template_id' => $design->id]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSee('hero-v-photo-top', false)
        ->assertSee('services-v-tiles', false)
        ->assertSee('about-v-highlight', false);
});

test('inner page layouts follow the homepage mix for each template', function () {
    expect(SectionVariants::complementary(['about' => 'highlight', 'services' => 'featured']))
        ->toMatchArray([
            'about_page' => 'band',
            'services_page' => 'featured',
            'mission' => 'band',
        ])
        ->and(SectionVariants::complementary(['about' => 'split', 'services' => 'cards']))
        ->toMatchArray([
            'about_page' => 'story',
            'services_page' => 'zigzag',
            'mission' => 'split',
            'header' => 'line',
        ])
        ->and(SectionVariants::complementary(['hero' => 'overlay']))
        ->toMatchArray(['header' => 'bar'])
        ->and(SectionVariants::complementary(['services' => 'numbered']))
        ->services_page->toBe('zigzag');
});

test('section variants ignore unknown values and skip defaults', function () {
    expect(SectionVariants::clean(['hero' => 'nope', 'services' => 'list', 'bogus' => 'x']))->toBe(['services' => 'list'])
        ->and(SectionVariants::bodyClasses(['hero' => 'classic', 'services' => 'list', 'gallery' => 'masonry']))
        ->toBe('services-v-list gallery-v-masonry');
});

test('team members can search the design catalog over json', function () {
    $user = User::factory()->create();
    app(EnsureDesignTemplateCatalog::class)->ensure();

    $this->actingAs($user)
        ->getJson(route('site-design-templates.index', [
            'current_team' => $user->currentTeam->slug,
            'industry' => 'dental',
        ]))
        ->assertOk()
        ->assertJsonPath('templates.0.slug', 'modern-minimal-clinic')
        ->assertJsonStructure(['templates', 'styles']);
});

test('site generation accepts a catalog template and applies its accent', function () {
    app(EnsureDesignTemplateCatalog::class)->ensure();

    $template = SiteDesignTemplate::query()->where('slug', 'editorial-dark-creative')->firstOrFail();
    $lead = Lead::factory()->create();

    GenerateLeadSite::dispatchSync($lead, $template->id);

    $site = $lead->fresh()->site;

    expect($site)->not->toBeNull()
        ->and($site->design_template_id)->toBe($template->id)
        ->and($site->template->value)->toBe('editorial-dark')
        ->and($site->content['accent'])->toBe('#f59e0b');
});

test('building a site from the lead route can pass a design template id', function () {
    app(EnsureDesignTemplateCatalog::class)->ensure();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $template = SiteDesignTemplate::query()->where('slug', 'modern-clean-pro')->firstOrFail();

    Queue::fake([GenerateLeadSite::class, FetchLeadReviews::class]);

    $this->actingAs($user)
        ->post(route('leads.site.store', [
            'current_team' => $user->currentTeam->slug,
            'lead' => $lead->id,
        ]), ['design_template_id' => $template->id])
        ->assertRedirect();

    Queue::assertPushed(GenerateLeadSite::class, function (GenerateLeadSite $job) use ($lead, $template) {
        return $job->lead->is($lead) && $job->designTemplateId === $template->id;
    });
});

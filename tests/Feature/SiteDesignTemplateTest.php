<?php

use App\Jobs\GenerateLeadSite;
use App\Models\Lead;
use App\Models\SiteDesignTemplate;
use App\Models\User;
use App\Services\SiteBuilder\EnsureDesignTemplateCatalog;
use Database\Seeders\SiteDesignTemplateSeeder;

test('the design catalog is seeded once and can be filtered by industry and style', function () {
    (new SiteDesignTemplateSeeder)->run();
    (new SiteDesignTemplateSeeder)->run();

    expect(SiteDesignTemplate::query()->count())->toBe(9);

    $plumbing = SiteDesignTemplate::query()->active()->filtered('plumbing')->ordered()->get();

    expect($plumbing)->not->toBeEmpty()
        ->and($plumbing->first()->slug)->toBe('modern-trade-services');

    $warm = SiteDesignTemplate::query()->active()->filtered(style: 'warm')->count();

    expect($warm)->toBe(2);
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

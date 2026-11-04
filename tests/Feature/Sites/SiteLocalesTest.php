<?php

use App\Ai\Agents\SiteLocaleCopyTranslator;
use App\Jobs\TranslateSiteLocale;
use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Services\SiteBuilder\SiteLocaleTranslator;
use App\Services\SiteBuilder\SiteRenderer;
use App\Services\SiteBuilder\SiteUrls;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;

test('published sites serve translated pages under a locale prefix', function () {
    $lead = Lead::factory()->create(['city' => 'Austin']);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'headline' => 'English headline',
            'locales' => ['es'],
            'translations' => [
                'es' => [
                    'headline' => 'Titular en español',
                ],
            ],
        ],
    ]);
    $site->forceFill(['published_at' => now()])->save();
    SiteDomain::factory()->verified()->for($site)->create(['hostname' => 'pros.example.com']);

    $this->get('https://pros.example.com/es')
        ->assertOk()
        ->assertSee('Titular en español')
        ->assertSee('hreflang="es"', escape: false)
        ->assertSee('hreflang="x-default"', escape: false);
});

test('locale translation can be queued from the dashboard', function () {
    Queue::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->postJson(route('sites.locales.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'locale' => 'fr',
        ])
        ->assertOk();

    expect($site->fresh()->content['locales'])->toContain('fr');

    Queue::assertPushed(TranslateSiteLocale::class);
});

test('a translation job stores the provider bundle', function () {
    config([
        'services.anthropic.key' => 'test-key',
        'services.anthropic.model' => 'claude-test',
    ]);

    SiteLocaleCopyTranslator::fake(fn () => new ToolCall(
        'tool_1',
        'SubmitLocaleCopy',
        ['copy_json' => json_encode(['headline' => 'Bonjour headline'], JSON_THROW_ON_ERROR)],
    ));

    $site = Site::factory()->create([
        'content' => [
            ...Site::factory()->make()->content,
            'headline' => 'Hello',
            'locales' => ['fr'],
        ],
    ]);

    (new TranslateSiteLocale($site, 'fr'))->handle(app(SiteLocaleTranslator::class));

    expect($site->fresh()->content['translations']['fr']['headline'])->toBe('Bonjour headline');
});

test('the renderer merges locale copy for inner pages', function () {
    $site = Site::factory()->create([
        'content' => [
            ...Site::factory()->make()->content,
            'about' => 'English about',
            'locales' => ['de'],
            'translations' => [
                'de' => ['about' => 'Deutsch about'],
            ],
        ],
    ]);

    $html = app(SiteRenderer::class)->render(
        $site,
        path: 'de/about',
        urls: SiteUrls::root('https://example.com', locale: 'de', defaultLocale: 'en'),
    );

    expect($html)->toContain('Deutsch about');
});

<?php

use App\Actions\Sites\GenerateSite;
use App\Models\Lead;
use App\Models\Site;
use App\Services\SiteBuilder\SiteBrandAssets;
use App\Services\SiteBuilder\SiteRenderer;
use App\Services\SiteBuilder\SiteUrls;
use Illuminate\Support\Facades\Storage;

test('generating a site creates favicon and og brand assets', function () {
    Storage::fake('public');

    $lead = Lead::factory()->create([
        'name' => 'Handyman Mike',
        'category' => 'Handyman',
        'city' => 'Austin',
        'photo_url' => 'https://cdn.example.com/van.jpg',
        'photo_paths' => null,
    ]);

    $site = app(GenerateSite::class)->handle($lead->fresh());
    $brand = $site->fresh()->content['brand'] ?? [];

    expect($brand)->toHaveKeys(SiteBrandAssets::KEYS)
        ->and($brand['favicon_svg'])->toBeString()->not->toBeEmpty()
        ->and($brand['og_image'])->toBeString()->not->toBeEmpty();

    foreach (SiteBrandAssets::KEYS as $key) {
        expect(Storage::disk('public')->exists($brand[$key]))->toBeTrue();
    }

    $og = Storage::disk('public')->get($brand['og_image']);
    $info = getimagesizefromstring($og);

    expect($info[0])->toBe(SiteBrandAssets::OG_WIDTH)
        ->and($info[1])->toBe(SiteBrandAssets::OG_HEIGHT);
});

test('preview pages serve the site favicon and branded og image instead of lead photos', function () {
    Storage::fake('public');

    $lead = Lead::factory()->create([
        'name' => 'Handyman Mike',
        'category' => 'Handyman',
        'city' => 'Austin',
        'photo_url' => 'https://cdn.example.com/van.jpg',
        'photo_paths' => null,
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    app(SiteBrandAssets::class)->refresh($site->fresh(['lead']));
    $site = $site->fresh(['lead']);
    $ogUrl = app(SiteBrandAssets::class)->urls($site->content)['og_image'];

    $html = app(SiteRenderer::class)->render($site, asPreview: true, urls: SiteUrls::preview($site, internal: true));

    expect($html)->toContain('rel="icon"')
        ->and($html)->toContain('type="image/svg+xml"')
        ->and($html)->toContain('apple-touch-icon')
        ->and($html)->toContain('og:image:width" content="1200"')
        ->and($html)->toContain('og:image:height" content="630"')
        ->and($html)->toContain('property="og:image" content="'.$ogUrl.'"')
        ->and($html)->not->toContain('property="og:image" content="https://cdn.example.com/van.jpg"');
});

test('existing sites without brand assets get them on first render', function () {
    Storage::fake('public');

    $lead = Lead::factory()->create(['name' => 'Austin Pro Plumbing', 'city' => 'Austin']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    expect($site->content['brand'] ?? null)->toBeNull();

    app(SiteRenderer::class)->render($site, urls: SiteUrls::root('https://pros.example.com'));

    $brand = $site->fresh()->content['brand'] ?? [];

    expect($brand)->toHaveKeys(SiteBrandAssets::KEYS);
});

test('og cards reuse the same template dimensions for any business name', function () {
    Storage::fake('public');

    $assets = app(SiteBrandAssets::class);

    foreach (['Ace Plumbing', 'Bright Smile Dental Care', 'Q'] as $name) {
        $lead = Lead::factory()->create(['name' => $name, 'city' => 'Dallas']);
        $site = Site::factory()->create(['lead_id' => $lead->id]);
        $assets->refresh($site->fresh(['lead']));
        $path = $site->fresh()->content['brand']['og_image'];
        $info = getimagesizefromstring(Storage::disk('public')->get($path));

        expect($info[0])->toBe(1200)->and($info[1])->toBe(630);
    }
});

test('absolute brand urls stay absolute for social crawlers', function () {
    config(['app.url' => 'https://app.example.com']);

    $urls = app(SiteBrandAssets::class);

    expect($urls->absoluteUrl('/storage/teams/1/leads/2/og.png'))
        ->toBe('https://app.example.com/storage/teams/1/leads/2/og.png')
        ->and($urls->absoluteUrl('https://res.cloudinary.com/demo/image.png'))
        ->toBe('https://res.cloudinary.com/demo/image.png');
});

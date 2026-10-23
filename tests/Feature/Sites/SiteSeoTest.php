<?php

use App\Models\SitePost;
use App\Services\SiteBuilder\SitePages;
use App\Services\SiteBuilder\SiteQualityChecker;
use App\Services\SiteBuilder\SiteRenderer;
use App\Services\SiteBuilder\SiteSeo;
use App\Services\SiteBuilder\SiteSitemap;
use App\Services\SiteBuilder\SiteUrls;

/**
 * @return list<array<string, mixed>>
 */
function structuredData(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $json) => json_decode($json, true), $matches[1]);
}

test('each page has its own title and description', function () {
    $site = multiPageSite();
    $seo = app(SiteSeo::class);
    $pages = app(SitePages::class);

    $titles = collect(['', 'about', 'services', 'services/drain-cleaning', 'contact'])
        ->map(fn (string $path) => $seo->title($site, $pages->resolve($site, $path)));

    expect($titles->unique())->toHaveCount(5)
        ->and($titles[0])->toBe('Austin Pro Plumbing | Plumber in Austin')
        ->and($titles[3])->toBe('Drain Cleaning in Austin | Austin Pro Plumbing')
        ->and($titles->every(fn (string $title) => mb_strlen($title) <= SiteSeo::TITLE_MAX))->toBeTrue();

    $description = $seo->description($site, $pages->resolve($site, 'services'));
    expect($description)->toContain('Emergency Repairs, Drain Cleaning')
        ->and(mb_strlen($description))->toBeLessThanOrEqual(SiteSeo::DESCRIPTION_MAX + 1);
});

test('previews are kept out of search engines', function () {
    $site = multiPageSite();

    $this->get(previewPage($site, 'about'))
        ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false)
        ->assertDontSee('rel="canonical"', escape: false);
});

test('a published page is indexable with a canonical link and social tags', function () {
    $site = multiPageSite();

    $html = app(SiteRenderer::class)->render($site, path: 'about', urls: SiteUrls::root('https://pros.example.com'));

    expect($html)->toContain('<link rel="canonical" href="https://pros.example.com/about">')
        ->and($html)->toContain('index, follow, max-image-preview:large')
        ->and($html)->toContain('<meta property="og:title" content="About Austin Pro Plumbing | Plumber in Austin">')
        ->and($html)->toContain('<meta name="twitter:card"');
});

test('pages describe the business with structured data', function () {
    $site = multiPageSite();
    $site->lead->update(['phone' => '555-0100', 'address' => '12 Main St, Austin', 'latitude' => 30.26, 'longitude' => -97.74]);

    $home = structuredData(app(SiteRenderer::class)->render($site->fresh(), urls: SiteUrls::root('https://pros.example.com')));
    $business = collect($home)->firstWhere('@type', 'LocalBusiness');

    expect($business['name'])->toBe('Austin Pro Plumbing')
        ->and($business['telephone'])->toBe('555-0100')
        ->and($business['address']['addressLocality'])->toBe('Austin')
        ->and($business['geo']['latitude'])->toBe(30.26)
        ->and(collect($home)->firstWhere('@type', 'BreadcrumbList'))->toBeNull();
});

test('secondary pages add breadcrumb, service and article markup', function () {
    $site = multiPageSite();
    SitePost::factory()->for($site)->create(['title' => 'Winter pipe tips', 'slug' => 'winter-pipe-tips']);
    $urls = SiteUrls::root('https://pros.example.com');
    $renderer = app(SiteRenderer::class);

    $service = structuredData($renderer->render($site, path: 'services/drain-cleaning', urls: $urls));
    $crumbs = collect($service)->firstWhere('@type', 'BreadcrumbList');

    expect(collect($crumbs['itemListElement'])->pluck('name')->all())->toBe(['Home', 'Services', 'Drain Cleaning'])
        ->and(collect($crumbs['itemListElement'])->pluck('item')->all())->toBe([
            'https://pros.example.com/',
            'https://pros.example.com/services',
            'https://pros.example.com/services/drain-cleaning',
        ])
        ->and(collect($service)->firstWhere('@type', 'Service')['name'])->toBe('Drain Cleaning');

    $post = structuredData($renderer->render($site, path: 'blog/winter-pipe-tips', urls: $urls));

    expect(collect($post)->firstWhere('@type', 'BlogPosting')['headline'])->toBe('Winter pipe tips');
});

test('frequently asked questions are marked up on the home page', function () {
    $site = multiPageSite(['faqs' => [['question' => 'Do you work weekends?', 'answer' => 'Yes, every day.']]]);

    $faq = collect(structuredData(app(SiteRenderer::class)->render($site, urls: SiteUrls::root('https://pros.example.com'))))->firstWhere('@type', 'FAQPage');

    expect($faq['mainEntity'][0]['name'])->toBe('Do you work weekends?');
});

test('structured data cannot break out of its script tag', function () {
    $site = multiPageSite(leadAttributes: ['name' => 'Pipes </script><script>alert(1)</script>']);

    $html = app(SiteRenderer::class)->render($site, urls: SiteUrls::root('https://pros.example.com'));

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and(structuredData($html)[0]['name'])->toBe('Pipes </script><script>alert(1)</script>');
});

test('the sitemap lists every page and robots.txt points to it', function () {
    $site = multiPageSite();
    SitePost::factory()->for($site)->create(['slug' => 'winter-pipe-tips']);
    SitePost::factory()->for($site)->draft()->create(['slug' => 'secret-draft']);
    $urls = SiteUrls::root('https://pros.example.com');

    $xml = app(SiteSitemap::class)->xml($site, $urls);

    expect($xml)->toContain('<loc>https://pros.example.com/</loc>')
        ->and($xml)->toContain('<loc>https://pros.example.com/services/emergency-repairs</loc>')
        ->and($xml)->toContain('<loc>https://pros.example.com/blog/winter-pipe-tips</loc>')
        ->and($xml)->not->toContain('secret-draft')
        ->and(simplexml_load_string($xml))->not->toBeFalse()
        ->and(app(SiteSitemap::class)->robots($urls))->toContain('Sitemap: https://pros.example.com/sitemap.xml');
});

test('the quality report flags thin service pages and missing local details', function () {
    $site = multiPageSite(leadAttributes: ['address' => null]);

    $checks = collect(app(SiteQualityChecker::class)->check($site)['checks'])->keyBy('key');

    expect($checks['seo-service-pages']['status'])->toBe('warn')
        ->and($checks['seo-service-pages']['detail'])->toContain('2 of 2')
        ->and($checks['seo-local']['status'])->toBe('warn')
        ->and($checks['seo-snippet']['status'])->toBe('pass');
});

test('detailed service pages pass the quality report', function () {
    $site = multiPageSite(['services' => [
        ['title' => 'Drain Cleaning', 'description' => 'Clear drains.', 'slug' => 'drain-cleaning', 'body' => str_repeat('We clear drains thoroughly. ', 10)],
    ]]);

    expect(collect(app(SiteQualityChecker::class)->check($site)['checks'])->firstWhere('key', 'seo-service-pages')['status'])->toBe('pass');
});

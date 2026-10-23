<?php

use App\Http\Middleware\ServePublishedSite;
use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\SitePost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @return array{0: Site, 1: SiteDomain}
 */
function liveSite(string $host = 'pros.example.com'): array
{
    $lead = Lead::factory()->create([
        'name' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
        'phone' => '555-0100',
        'photo_url' => null,
        'photo_paths' => null,
    ]);

    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'headline' => 'Plumbing that just works',
            'about' => 'We fix pipes fast and keep the mess to a minimum, serving homes across Austin.',
        ],
    ]);
    $site->forceFill(['published_at' => now()])->save();

    return [$site, SiteDomain::factory()->verified()->for($site)->create(['hostname' => $host])];
}

test('a verified domain serves the published website', function () {
    liveSite();

    $this->get('https://pros.example.com')
        ->assertOk()
        ->assertSee('Plumbing that just works')
        ->assertSee('<link rel="canonical" href="https://pros.example.com/">', escape: false)
        ->assertSee('index, follow', escape: false)
        ->assertDontSee('<div class="claim-bar"', escape: false)
        ->assertDontSee('noindex', escape: false)
        ->assertHeaderMissing('Set-Cookie');
});

test('inner pages have their own canonical address on the domain', function () {
    liveSite();

    $this->get('https://pros.example.com/services/emergency-repairs')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://pros.example.com/services/emergency-repairs">', escape: false);
});

test('pages are sent with caching headers and answer repeat visits with not modified', function () {
    liveSite();

    $first = $this->get('https://pros.example.com/about')->assertOk();

    expect($first->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=300')->toContain('stale-while-revalidate');

    $this->withHeaders(['If-None-Match' => $first->headers->get('ETag')])
        ->get('https://pros.example.com/about')
        ->assertStatus(304);
});

test('the domain serves its own robots.txt and sitemap', function () {
    $site = liveSite()[0];
    SitePost::factory()->for($site)->create(['slug' => 'winter-pipe-tips']);

    $this->get('https://pros.example.com/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: https://pros.example.com/sitemap.xml');

    $this->get('https://pros.example.com/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>https://pros.example.com/blog/winter-pipe-tips</loc>', escape: false);
});

test('unknown, unverified and unpublished domains are not found', function () {
    [$site] = liveSite();
    SiteDomain::factory()->for($site)->create(['hostname' => 'pending.example.com']);

    $this->get('https://stranger.example.com')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex');
    $this->get('https://pending.example.com')->assertNotFound();

    $site->forceFill(['published_at' => null])->save();

    $this->get('https://pros.example.com')->assertNotFound();
});

test('missing pages return not found on the domain', function () {
    liveSite();

    $this->get('https://pros.example.com/pricing')->assertNotFound();
    $this->get('https://pros.example.com/services/nope')->assertNotFound();
});

test('other domains redirect to the main address', function () {
    [$site, $main] = liveSite();
    $main->forceFill(['verified_at' => now()->subDay()])->save();
    SiteDomain::factory()->verified()->for($site)->create(['hostname' => 'www.pros.example.com']);

    $this->get('https://www.pros.example.com/about?ref=1')
        ->assertStatus(301)
        ->assertRedirect('https://pros.example.com/about?ref=1');
});

test('plain http is sent to https', function () {
    liveSite();

    $this->get('http://pros.example.com/about')
        ->assertStatus(301)
        ->assertRedirect('https://pros.example.com/about');
});

test('trailing slashes redirect to the clean address', function () {
    liveSite();

    // The test client strips trailing slashes, so hand the middleware a raw request.
    $response = app(ServePublishedSite::class)->handle(
        Request::create('https://pros.example.com/about/?ref=1'),
        fn () => response('next'),
    );

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('https://pros.example.com/about?ref=1');
});

test('customer domains only answer reads', function () {
    liveSite();

    $this->post('https://pros.example.com/about')->assertStatus(405)->assertHeader('Allow', 'GET, HEAD');
});

test('the dashboard host is never treated as a customer site', function () {
    liveSite();

    $this->get('/')->assertOk();
    $this->get(route('login'))->assertOk();
});

test('visits through the domain do not add preview views', function () {
    [$site] = liveSite();

    $this->get('https://pros.example.com')->assertOk();

    expect($site->fresh()->views_count)->toBe(0);
});

test('edits show up on the live site straight away', function () {
    [$site] = liveSite();

    $this->get('https://pros.example.com')->assertSee('Plumbing that just works');

    // A change that bypasses the model leaves the cached page in place.
    DB::table('sites')->where('id', $site->id)->update(['content' => json_encode([...$site->content, 'headline' => 'Quietly changed'])]);
    $this->get('https://pros.example.com')->assertSee('Plumbing that just works')->assertDontSee('Quietly changed');

    $site->refresh()->update(['content' => [...$site->content, 'headline' => 'Brand new headline']]);
    $this->get('https://pros.example.com')->assertSee('Brand new headline')->assertDontSee('Plumbing that just works');
});

test('publishing a blog post updates the sitemap and menu', function () {
    [$site] = liveSite();

    $this->get('https://pros.example.com/sitemap.xml')->assertDontSee('/blog');

    SitePost::factory()->for($site)->create(['slug' => 'fresh-post']);

    $this->get('https://pros.example.com/sitemap.xml')->assertSee('/blog/fresh-post');
    $this->get('https://pros.example.com')->assertSee('href="/blog"', escape: false);
});

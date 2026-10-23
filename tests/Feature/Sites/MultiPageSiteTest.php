<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\SitePost;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $content
 * @param  array<string, mixed>  $leadAttributes
 */
function multiPageSite(array $content = [], array $leadAttributes = []): Site
{
    $lead = Lead::factory()->create([
        'name' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
        'photo_url' => null,
        'photo_paths' => null,
        ...$leadAttributes,
    ]);

    return Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'about' => "We fix pipes fast and keep the mess to a minimum.\n\nFamily run since day one, serving homes across Austin.",
            'services' => [
                ['title' => 'Emergency Repairs', 'description' => 'Fast help when something goes wrong.', 'slug' => 'emergency-repairs', 'body' => 'We answer leaks at any hour.'],
                ['title' => 'Drain Cleaning', 'description' => 'Clear drains, no mess.', 'slug' => 'drain-cleaning'],
            ],
            ...$content,
        ],
    ]);
}

function previewPage(Site $site, string $path = ''): string
{
    return route('sites.preview', array_filter(['site' => $site->slug, 'path' => $path ?: null, 'internal' => 1]));
}

test('the menu lists the pages the site has and links each one', function () {
    $site = multiPageSite();

    $this->get(previewPage($site))
        ->assertOk()
        ->assertSeeInOrder([
            "/p/{$site->slug}/about",
            "/p/{$site->slug}/services",
            "/p/{$site->slug}/contact",
        ], escape: false)
        ->assertDontSee("/p/{$site->slug}/gallery", escape: false)
        ->assertDontSee("/p/{$site->slug}/blog", escape: false);
});

test('secondary pages open with the breadcrumb above the heading', function (string $path, string $heading, array $trail) {
    $site = multiPageSite();

    $this->get(previewPage($site, $path))
        ->assertOk()
        ->assertSee($heading)
        ->assertSeeInOrder(['class="hero page-hero"', 'aria-label="Breadcrumb"', ...$trail, '<h1>'], escape: false);
})->with([
    'about' => ['about', 'About Austin Pro Plumbing', ['Home', 'About']],
    'services' => ['services', 'Plumber Services in Austin', ['Home', 'Services']],
    'a service' => ['services/drain-cleaning', 'Drain Cleaning in Austin', ['Home', 'Services', 'Drain Cleaning']],
    'contact' => ['contact', 'Contact Austin Pro Plumbing', ['Home', 'Contact']],
]);

test('the home page has no breadcrumb', function () {
    $site = multiPageSite();

    $this->get(previewPage($site))->assertOk()->assertDontSee('aria-label="Breadcrumb"', escape: false);
});

test('every service gets its own page linked from the services page', function () {
    $site = multiPageSite();

    $this->get(previewPage($site, 'services'))
        ->assertSee("/p/{$site->slug}/services/emergency-repairs", escape: false)
        ->assertSee("/p/{$site->slug}/services/drain-cleaning", escape: false);

    $this->get(previewPage($site, 'services/emergency-repairs'))
        ->assertOk()
        ->assertSee('We answer leaks at any hour.')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('a service without a stored slug still gets a stable page from its title', function () {
    $site = multiPageSite(['services' => [['title' => 'Water Heaters', 'description' => 'Install and repair.']]]);

    $this->get(previewPage($site, 'services/water-heaters'))->assertOk()->assertSee('Install and repair.');
});

test('unknown pages and services return not found', function (string $path) {
    $site = multiPageSite();

    $this->get(previewPage($site, $path))->assertNotFound();
})->with(['unknown page' => 'pricing', 'unknown service' => 'services/nope', 'too deep' => 'services/a/b', 'gallery without photos' => 'gallery', 'blog without posts' => 'blog']);

test('pages can be switched off', function () {
    $site = multiPageSite(['pages' => ['services']]);

    $this->get(previewPage($site, 'about'))->assertNotFound();
    $this->get(previewPage($site, 'services'))->assertOk();
    $this->get(previewPage($site, 'contact'))->assertOk();
});

test('the gallery page appears once the business has enough photos', function () {
    Storage::fake('public');

    $site = multiPageSite(leadAttributes: ['photo_paths' => ['teams/1/leads/1/one.jpg', 'teams/1/leads/1/two.jpg']]);

    $this->get(previewPage($site, 'gallery'))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url('teams/1/leads/1/two.jpg'), escape: false);

    $this->get(previewPage($site))->assertSee("/p/{$site->slug}/gallery", escape: false);
});

test('the blog only shows published posts', function () {
    $site = multiPageSite();
    SitePost::factory()->for($site)->create(['title' => 'Winter pipe tips', 'slug' => 'winter-pipe-tips', 'body' => "Lag your pipes.\n\nTurn off outdoor taps."]);
    SitePost::factory()->for($site)->draft()->create(['title' => 'Secret draft', 'slug' => 'secret-draft']);
    SitePost::factory()->for($site)->create(['title' => 'Scheduled later', 'slug' => 'scheduled-later', 'published_at' => now()->addWeek()]);

    $this->get(previewPage($site, 'blog'))
        ->assertOk()
        ->assertSee('Winter pipe tips')
        ->assertDontSee('Secret draft')
        ->assertDontSee('Scheduled later');

    $this->get(previewPage($site, 'blog/winter-pipe-tips'))
        ->assertOk()
        ->assertSee('Turn off outdoor taps.')
        ->assertSeeInOrder(['Home', 'Blog', 'Winter pipe tips', '<h1>'], escape: false);

    $this->get(previewPage($site, 'blog/secret-draft'))->assertNotFound();
    $this->get(previewPage($site, 'blog/scheduled-later'))->assertNotFound();
});

test('blog post text is escaped', function () {
    $site = multiPageSite();
    SitePost::factory()->for($site)->create(['slug' => 'xss', 'body' => '<script>alert(1)</script>']);

    $this->get(previewPage($site, 'blog/xss'))
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
});

test('only visits to the home page are counted', function () {
    $site = multiPageSite();

    $this->get(route('sites.preview', $site->slug))->assertOk();
    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'about']))->assertOk();

    expect($site->fresh()->views_count)->toBe(1);
});

test('internal previews keep the internal flag on every link', function () {
    $site = multiPageSite();

    $this->get(previewPage($site, 'about'))
        ->assertSee("/p/{$site->slug}/services?internal=1", escape: false);
});

test('the export contains a folder for every page', function () {
    $site = multiPageSite();
    SitePost::factory()->for($site)->create(['slug' => 'winter-pipe-tips']);
    $user = User::factory()->create();
    $site->forceFill(['team_id' => $user->currentTeam->id])->save();
    $site->lead->forceFill(['team_id' => $user->currentTeam->id])->save();

    $response = $this->actingAs($user)
        ->get(route('sites.export', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertOk();

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $files = collect(range(0, $zip->numFiles - 1))->map(fn (int $index) => $zip->getNameIndex($index))->all();

    expect($files)->toContain('index.html', 'about/index.html', 'services/index.html', 'services/emergency-repairs/index.html', 'blog/index.html', 'blog/winter-pipe-tips/index.html', 'contact/index.html', 'robots.txt');

    $about = $zip->getFromName('about/index.html');
    expect($about)->toContain('href="/services/"')
        ->and($about)->not->toContain('noindex')
        ->and($about)->not->toContain('<div class="claim-bar"');
});

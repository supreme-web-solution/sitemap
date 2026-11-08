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
                [
                    'title' => 'Emergency Repairs',
                    'description' => 'Fast help when something goes wrong. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use. Get in touch if you want to check whether this is the right fit.',
                    'slug' => 'emergency-repairs',
                    'body' => "We answer leaks at any hour.\n\nFast help when something goes wrong. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use.\n\nYou say what you need, we look at the job, and we agree the next step before anything begins.",
                ],
                [
                    'title' => 'Drain Cleaning',
                    'description' => 'Clear drains, no mess. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use. Get in touch if you want to check whether this is the right fit.',
                    'slug' => 'drain-cleaning',
                    'body' => "Clear drains, no mess.\n\nThe work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use.\n\nYou say what you need, we look at the job, and we agree the next step before anything begins.",
                ],
                [
                    'title' => 'Water Heaters',
                    'description' => 'Repair and replacement for hot water. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use. Get in touch if you want to check whether this is the right fit.',
                    'slug' => 'water-heaters',
                    'body' => "Repair and replacement for hot water.\n\nThe work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use.\n\nYou say what you need, we look at the job, and we agree the next step before anything begins.",
                ],
                [
                    'title' => 'Installations',
                    'description' => 'New fixtures fitted properly. The work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use. Get in touch if you want to check whether this is the right fit.',
                    'slug' => 'installations',
                    'body' => "New fixtures fitted properly.\n\nThe work is talked through before it starts, so you know what will be done in Austin. When the job is finished, the space is left tidy and ready to use.\n\nYou say what you need, we look at the job, and we agree the next step before anything begins.",
                ],
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
    'about' => ['about', 'About us', ['Home', 'About']],
    'services' => ['services', 'Our Services', ['Home', 'Services']],
    'a service' => ['services/drain-cleaning', 'Drain Cleaning', ['Home', 'Services', 'Drain Cleaning']],
    'contact' => ['contact', 'Contact', ['Home', 'Contact']],
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
        ->assertSee('agree the next step before anything begins')
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('index, follow, max-image-preview:large', escape: false);
});

test('a service without a stored slug still gets a stable page from its title', function () {
    $site = multiPageSite(['services' => [['title' => 'Water Heaters', 'description' => 'Install and repair.']]]);

    $this->get(previewPage($site, 'services/water-heaters'))->assertOk()->assertSee('Install and repair.');
});

test('unknown pages and services return not found', function (string $path) {
    $site = multiPageSite();

    $this->get(previewPage($site, $path))->assertNotFound();
})->with(['unknown page' => 'pricing', 'unknown service' => 'services/nope', 'too deep' => 'services/a/b', 'gallery when off' => 'gallery', 'blog when off' => 'blog']);

test('pages can be switched off', function () {
    $site = multiPageSite(['pages' => ['services']]);

    $this->get(previewPage($site, 'about'))->assertNotFound();
    $this->get(previewPage($site, 'services'))->assertOk();
    $this->get(previewPage($site, 'contact'))->assertOk();
});

test('an enabled gallery page shows even before photos are added', function () {
    $site = multiPageSite(['pages' => ['about', 'services', 'gallery']]);

    $this->get(previewPage($site, 'gallery'))
        ->assertOk()
        ->assertSee('Photos for Austin Pro Plumbing will show here soon.');

    $this->get(previewPage($site))->assertSee("/p/{$site->slug}/gallery", escape: false);
});

test('enabling the gallery page also shows the home gallery section once photos exist', function () {
    Storage::fake('public');

    $site = multiPageSite(
        [
            'pages' => ['about', 'services', 'gallery'],
            'sections' => ['services', 'about', 'contact'],
        ],
        ['photo_paths' => ['teams/1/leads/1/one.jpg']],
    );

    $this->get(previewPage($site))
        ->assertOk()
        ->assertSee('id="gallery"', false)
        ->assertSee(Storage::disk('public')->url('teams/1/leads/1/one.jpg'), escape: false)
        ->assertSee("/p/{$site->slug}/gallery", escape: false);
});

test('the gallery page shows photos when the page is enabled', function () {
    Storage::fake('public');

    $site = multiPageSite(
        ['pages' => ['about', 'services', 'gallery']],
        ['photo_paths' => ['teams/1/leads/1/one.jpg', 'teams/1/leads/1/two.jpg']],
    );

    $this->get(previewPage($site, 'gallery'))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url('teams/1/leads/1/two.jpg'), escape: false);

    $this->get(previewPage($site))->assertSee("/p/{$site->slug}/gallery", escape: false);
});

test('the blog page shows an add post control while editing', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'name' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'pages' => ['about', 'services', 'blog'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('sites.preview', ['site' => $site->slug, 'path' => 'blog', 'internal' => 1, 'editor' => 1]))
        ->assertOk()
        ->assertSee('data-edit-panel="posts"', false)
        ->assertSee('Add a post');
});

test('a service detail page uses that service card image in the hero', function () {
    Storage::fake('public');
    Storage::disk('public')->put('teams/1/leads/1/repairs.jpg', 'service-photo');

    $site = multiPageSite([
        'services' => [[
            'title' => 'Emergency Repairs',
            'description' => 'Fast help when something goes wrong.',
            'slug' => 'emergency-repairs',
            'body' => 'We answer leaks at any hour.',
            'image_path' => 'teams/1/leads/1/repairs.jpg',
        ]],
    ]);

    $url = Storage::disk('public')->url('teams/1/leads/1/repairs.jpg');

    $this->get(previewPage($site, 'services/emergency-repairs'))
        ->assertOk()
        ->assertSee($url, escape: false)
        ->assertSee('class="hero-image"', escape: false);
});

test('the blog only shows published posts', function () {
    $site = multiPageSite(['pages' => ['about', 'services', 'blog']]);
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
    $site = multiPageSite(['pages' => ['about', 'services', 'blog']]);
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
    $site = multiPageSite(['pages' => ['about', 'services', 'blog']]);
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
    $home = $zip->getFromName('index.html');
    $service = $zip->getFromName('services/emergency-repairs/index.html');
    expect($about)->toContain('href="../services/index.html"')
        ->and($about)->toContain('href="../index.html"')
        ->and($about)->not->toContain('href="/')
        ->and($home)->toContain('href="about/index.html"')
        ->and($service)->toContain('href="../../index.html"')
        ->and($about)->not->toContain('noindex')
        ->and($about)->not->toContain('<div class="claim-bar"');
});

<?php

use App\Enums\SiteTemplate;
use App\Enums\TeamRole;
use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDesignTemplate;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\SiteDesignTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('anyone with the link can view the live preview and the visit is counted', function () {
    $this->freezeSecond();

    $owner = User::factory()->create(['email' => 'agency@example.com']);
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $lead = Lead::factory()->create(['team_id' => $team->id, 'name' => 'Austin Pro Plumbing']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Austin Pro Plumbing')
        ->assertSee($site->content['headline'])
        ->assertSee('Claim this website')
        ->assertSee('mailto:agency@example.com', escape: false);

    expect($site->fresh())
        ->views_count->toBe(1)
        ->last_viewed_at->toEqual(now());
});

test('internal previews from the editor are not counted as prospect views', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))->assertOk();

    expect($site->fresh()->views_count)->toBe(0);
});

test('website content is escaped in the preview', function () {
    $site = Site::factory()->create([
        'content' => [
            ...Site::factory()->make()->content,
            'headline' => '<script>alert(1)</script>',
        ],
    ]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
});

test('text on the brand colour stays readable', function (string $accent, string $expectedText) {
    $site = Site::factory()->create([
        'content' => [...Site::factory()->make()->content, 'accent' => $accent],
    ]);

    $this->get(route('sites.preview', $site->slug))
        ->assertSee("--on-accent: {$expectedText};", escape: false);
})->with([
    'dark blue brand' => ['#2563eb', '#ffffff'],
    'bright yellow brand' => ['#facc15', '#0c0a09'],
]);

test('sections render in the planned order', function () {
    $site = Site::factory()->create([
        'content' => [...Site::factory()->make()->content, 'sections' => ['about', 'services', 'contact']],
    ]);

    $this->get(route('sites.preview', $site->slug))
        ->assertSeeInOrder(['id="about"', 'id="services"', 'class="cta-band"', 'class="site-footer"'], escape: false)
        ->assertDontSee('id="contact"', escape: false);
});

test('the about page has a split story, why choose us, and a mission by default', function () {
    $lead = Lead::factory()->create(['name' => 'Austin Pro Plumbing', 'category' => 'Plumber', 'city' => 'Austin']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'about', 'internal' => 1]))
        ->assertOk()
        ->assertSee('about-story', escape: false)
        ->assertSee('about-portrait', escape: false)
        ->assertSee('id="why"', escape: false)
        ->assertSee('id="mission"', escape: false)
        ->assertSee('What we stand for')
        ->assertSee('See what we offer')
        ->assertSee('class="hero-actions"', escape: false)
        ->assertSee('About us');
});

test('secondary pages use a photo hero with homepage-style actions', function () {
    Storage::fake('public');

    $lead = Lead::factory()->create([
        'photo_paths' => ['teams/1/leads/1/one.jpg'],
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'about', 'internal' => 1]))
        ->assertOk()
        ->assertSee('class="hero page-hero"', escape: false)
        ->assertSee('class="hero-image"', escape: false)
        ->assertSee('class="hero-actions"', escape: false)
        ->assertSee('Get a Free Estimate')
        ->assertSee('Call Now')
        ->assertSee('has-overlay-nav', escape: false)
        ->assertSee('.site-header { position: fixed;', escape: false)
        ->assertSee('is-scrolled', escape: false)
        ->assertSee('.nav a::after', escape: false);
});

test('the services page alternates the image side and can keep every image on the right', function () {
    $content = Site::factory()->make()->content;
    $content['services'] = [
        ['title' => 'General Repairs', 'description' => 'Doors, walls, and the small jobs in between.', 'slug' => 'general-repairs'],
        ['title' => 'Furniture Assembly', 'description' => 'Flat-pack furniture assembled and ready to use.', 'slug' => 'furniture-assembly'],
    ];
    $content['variants'] = ['services_page' => 'zigzag'];
    $site = Site::factory()->create(['content' => $content]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'services']))
        ->assertOk()
        ->assertSeeInOrder(['General Repairs', 'class="service-feature is-alt"', 'Furniture Assembly'], false)
        ->assertSee('.service-feature.is-alt .service-feature__copy { order: 2; }', false);

    $content['variants'] = ['services_page' => 'stack'];
    $site->update(['content' => $content]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'services']))
        ->assertOk()
        ->assertSee('services_page-v-stack', false)
        ->assertSee('.services_page-v-stack .service-feature.is-alt .service-feature__copy { order: 0; }', false);
});

test('the services page lists each offer as a left-right row with two actions', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'services', 'internal' => 1]))
        ->assertOk()
        ->assertSee('class="service-feature', escape: false)
        ->assertSee('btn btn-primary', escape: false)
        ->assertSee('btn btn-secondary', escape: false)
        ->assertSee('Learn more')
        ->assertSee('Get a Free Estimate');
});

test('homepage services section has a primary cta and a view-all button', function () {
    $site = Site::factory()->create();

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSee('class="section-actions"', escape: false)
        ->assertSee('btn btn-primary', escape: false)
        ->assertSee('btn btn-secondary', escape: false)
        ->assertSee('View all services');
});

test('the gallery is left out until the business has enough photos', function () {
    $lead = Lead::factory()->create(['photo_url' => 'https://example.com/cover.jpg', 'photo_paths' => null]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [...Site::factory()->make()->content, 'sections' => ['gallery', 'services']],
    ]);

    $this->get(route('sites.preview', $site->slug))
        ->assertDontSee('id="gallery"', escape: false)
        ->assertSee('class="cta-band"', escape: false);
});

test('the owner logo and photos are used on the site', function () {
    Storage::fake('public');

    $lead = Lead::factory()->create([
        'logo_path' => 'teams/1/leads/1/logo.png',
        'photo_paths' => ['teams/1/leads/1/one.jpg', 'teams/1/leads/1/two.jpg'],
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [...Site::factory()->make()->content, 'sections' => ['gallery', 'services']],
    ]);

    $this->get(route('sites.preview', $site->slug))
        ->assertSee(Storage::disk('public')->url('teams/1/leads/1/logo.png'), escape: false)
        ->assertSee('id="gallery"', escape: false)
        ->assertSee(Storage::disk('public')->url('teams/1/leads/1/two.jpg'), escape: false)
        ->assertSee('data-gallery-open', escape: false)
        ->assertSee('id="gallery-lightbox"', escape: false);
});

test('uploaded website photos are stored on the public disk under the team lead folder', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'slot' => 'photo',
            'image' => UploadedFile::fake()->image('gallery.jpg', 800, 600),
        ])
        ->assertOk();

    $path = $lead->fresh()->photo_paths[0];

    expect($path)->toStartWith("teams/{$user->currentTeam->id}/leads/{$lead->id}/");
    Storage::disk('public')->assertExists($path);
});

test('footer social links use icons including a fallback for unknown platforms', function () {
    $lead = Lead::factory()->create([
        'social_links' => [
            'instagram' => 'https://instagram.com/example',
            'facebook' => 'https://facebook.com/example',
            'customforum' => 'https://example.com/forum',
        ],
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->get(route('sites.preview', $site->slug))
        ->assertSee('footer-social__link', escape: false)
        ->assertSee('aria-label="Social"', escape: false)
        ->assertSee('https://instagram.com/example', escape: false)
        ->assertSee('>Customforum</span>', escape: false);
});

test('an unknown preview link returns not found', function () {
    $this->get(route('sites.preview', 'does-not-exist'))->assertNotFound();
});

test('the pre-footer cta sits above the footer on every page except contact', function () {
    $site = Site::factory()->create([
        'content' => [...Site::factory()->make()->content, 'cta_label' => 'Get in Touch'],
    ]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSeeInOrder(['class="cta-band"', 'class="site-footer"'], escape: false);

    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'about', 'internal' => 1]))
        ->assertOk()
        ->assertSeeInOrder(['class="cta-band"', 'class="site-footer"'], escape: false);

    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'contact', 'internal' => 1]))
        ->assertOk()
        ->assertSee('Get in Touch')
        ->assertDontSee('<section class="cta-band">', escape: false);
});

test('the homepage includes how it works, why choose us, service area and a column footer', function () {
    $lead = Lead::factory()->create([
        'name' => 'Austin Pro Plumbing',
        'city' => 'Austin',
        'state' => 'TX',
        'phone' => '555-0100',
        'opening_hours' => ['Monday' => '9:00 AM - 5:00 PM'],
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSee('id="process"', escape: false)
        ->assertSee('Get in touch')
        ->assertSee('id="why"', escape: false)
        ->assertSee('Local in Austin')
        ->assertSee('id="areas"', escape: false)
        ->assertSee('Austin')
        ->assertSee('>Explore<', escape: false)
        ->assertSee('Emergency Repairs')
        ->assertSee('Monday');
});

test('every template puts one call to action on the right of the navbar', function (SiteTemplate $template) {
    $site = Site::factory()->create([
        'template' => $template,
        'content' => [
            ...Site::factory()->make()->content,
            'cta_label' => 'Book a visit',
        ],
    ]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertSee('class="btn btn-primary header-cta"', escape: false)
        ->assertSee('class="btn btn-primary mobile-cta"', escape: false)
        ->assertSee('Book a visit')
        ->assertSee('overflow-x: clip', escape: false)
        ->assertDontSee('header-call', escape: false);
})->with(SiteTemplate::cases());

test('editorial dark keeps area and address text on a dark surface', function () {
    $lead = Lead::factory()->create([
        'name' => 'Handyman Mike',
        'city' => 'Austin',
        'neighborhood' => 'North Loop',
        'state' => 'Texas',
        'address' => '5211 Eilers Ave, Austin, TX 78751, USA',
        'opening_hours' => ['Monday' => '8 AM to 6 PM'],
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'template' => SiteTemplate::EditorialDark,
    ]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertSee('Handyman Mike')
        ->assertSee('North Loop')
        ->assertSee('5211 Eilers Ave, Austin, TX 78751, USA')
        ->assertSee('8 AM to 6 PM')
        ->assertSee('.location-card, .area-chips li { background: color-mix(in srgb, var(--accent) 16%, var(--surface)); color: var(--ink); }', escape: false)
        ->assertDontSee('background: color-mix(in srgb, var(--accent) 8%, #fff)', escape: false)
        ->assertDontSee('background: color-mix(in srgb, var(--accent) 6%, #fff)', escape: false);
});

test('a site can override its catalog layout for one section', function () {
    (new SiteDesignTemplateSeeder)->run();

    $design = SiteDesignTemplate::query()->where('slug', 'professional-advisor')->firstOrFail();
    $site = Site::factory()->create([
        'design_template_id' => $design->id,
        'content' => [
            ...Site::factory()->make()->content,
            'variants' => ['hero' => 'overlay', 'footer' => 'cta'],
        ],
    ]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSee('hero-v-overlay services-v-numbered', escape: false)
        ->assertSee('footer-v-cta', escape: false);
});

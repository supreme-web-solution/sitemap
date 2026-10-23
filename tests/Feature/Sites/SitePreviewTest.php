<?php

use App\Enums\TeamRole;
use App\Models\Lead;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
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
        ->assertSeeInOrder(['id="about"', 'id="services"', 'id="contact"'], escape: false);
});

test('the gallery is left out until the business has enough photos', function () {
    $lead = Lead::factory()->create(['photo_url' => 'https://example.com/cover.jpg', 'photo_paths' => null]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [...Site::factory()->make()->content, 'sections' => ['gallery', 'services']],
    ]);

    $this->get(route('sites.preview', $site->slug))
        ->assertDontSee('id="gallery"', escape: false)
        ->assertSee('id="contact"', escape: false);
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
        ->assertSee(Storage::disk('public')->url('teams/1/leads/1/two.jpg'), escape: false);
});

test('an unknown preview link returns not found', function () {
    $this->get(route('sites.preview', 'does-not-exist'))->assertNotFound();
});

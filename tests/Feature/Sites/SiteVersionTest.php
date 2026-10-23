<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteBuilder\SiteQualityChecker;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: Site, 1: User}
 */
function editableSite(): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    return [Site::factory()->create(['lead_id' => $lead->id]), $user];
}

/**
 * @return array<string, mixed>
 */
function sitePayload(Site $site, string $headline): array
{
    return [
        'template' => $site->template->value,
        'accent' => $site->content['accent'],
        'headline' => $headline,
        'tagline' => $site->content['tagline'],
        'cta_label' => $site->content['cta_label'],
        'about' => $site->content['about'],
        'sections' => $site->content['sections'],
        'services' => $site->content['services'],
        'faqs' => [],
    ];
}

test('saving a changed site keeps the previous version', function () {
    [$site, $user] = editableSite();
    $original = $site->content['headline'];

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), sitePayload($site, 'A brand new headline'))
        ->assertRedirect();

    $version = $site->versions()->sole();

    expect($site->fresh()->content['headline'])->toBe('A brand new headline')
        ->and($version->content['headline'])->toBe($original)
        ->and($version->user_id)->toBe($user->id);
});

test('saving without changes does not create a version', function () {
    [$site, $user] = editableSite();

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), sitePayload($site, $site->content['headline']))
        ->assertRedirect();

    expect($site->versions()->count())->toBe(0);
});

test('an earlier version can be restored and the current state is kept as a version', function () {
    [$site, $user] = editableSite();
    $original = $site->content['headline'];
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)->put(route('sites.update', ['current_team' => $slug, 'site' => $site->id]), sitePayload($site, 'Changed headline'));

    $version = $site->versions()->sole();

    $this->actingAs($user)
        ->post(route('sites.versions.restore', ['current_team' => $slug, 'site' => $site->id, 'version' => $version->id]))
        ->assertRedirect();

    expect($site->fresh()->content['headline'])->toBe($original)
        ->and($site->versions()->count())->toBe(2);
});

test('only the newest versions are kept', function () {
    [$site] = editableSite();

    foreach (range(1, Site::MAX_VERSIONS + 5) as $i) {
        $site->snapshot(null, "v{$i}");
    }

    expect($site->versions()->count())->toBe(Site::MAX_VERSIONS)
        ->and($site->versions()->oldest('id')->first()->label)->toBe('v6');
});

test('a version from another site cannot be restored', function () {
    [$site, $user] = editableSite();
    [$other] = editableSite();
    $foreign = $other->snapshot();

    $this->actingAs($user)
        ->post(route('sites.versions.restore', ['current_team' => $user->currentTeam->slug, 'site' => $site->id, 'version' => $foreign->id]))
        ->assertNotFound();
});

test('the editor shows a quality report and the version history', function () {
    [$site, $user] = editableSite();
    $site->snapshot($user->id, 'Initial');

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('quality.checks')
            ->has('versions', 1)
            ->where('versions.0.label', 'Initial'));
});

test('the quality checker warns about claims the business never confirmed', function () {
    $lead = Lead::factory()->create(['description' => 'We are licensed and insured.', 'phone' => '555-0100']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);
    $site->update(['content' => [
        ...$site->content,
        'tagline' => 'Licensed, insured and available 24/7 with a satisfaction guarantee',
    ]]);

    $claims = collect(app(SiteQualityChecker::class)->check($site->fresh())['checks'])->firstWhere('key', 'claims');

    expect($claims['status'])->toBe('warn')
        ->and($claims['detail'])->toContain('24/7', 'guarantee')
        ->not->toContain('licensed')
        ->not->toContain('insured');
});

test('the quality checker fails a site with no way to contact the business', function () {
    $lead = Lead::factory()->create(['phone' => null, 'email' => null]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $contact = collect(app(SiteQualityChecker::class)->check($site)['checks'])->firstWhere('key', 'contact');

    expect($contact['status'])->toBe('fail');
});

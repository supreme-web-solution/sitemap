<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Services\SiteBuilder\SiteStaging;

/**
 * @return array{0: Site, 1: User, 2: array{current_team: string, site: int}>
 */
function stagingSite(array $leadOverrides = []): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(array_merge([
        'phone' => '555-0100',
        'email' => 'hello@example.com',
    ], $leadOverrides));
    $site = Site::factory()->create(['lead_id' => $lead->id, 'slug' => 'acme-plumbing']);

    return [$site, $user, ['current_team' => $user->currentTeam->slug, 'site' => $site->id]];
}

beforeEach(function () {
    config([
        'sites.staging_base_host' => 'staging.test',
        'app.url' => 'http://localhost',
    ]);
});

test('staging hostname resolves from host and site slug', function () {
    expect(SiteStaging::slugFromHost('acme-plumbing.staging.test'))->toBe('acme-plumbing')
        ->and(SiteStaging::slugFromHost('other.staging.test'))->toBe('other')
        ->and(SiteStaging::slugFromHost('pros.example.com'))->toBeNull();
});

test('a site can be published to staging without a custom domain', function () {
    [$site, $user, $args] = stagingSite();

    $this->actingAs($user)->post(route('sites.staging.store', $args))->assertRedirect();

    expect($site->fresh()->isStagingPublished())->toBeTrue();
});

test('staging serves production-like pages with noindex', function () {
    [$site] = stagingSite();
    $site->forceFill([
        'staging_published_at' => now(),
        'content' => [
            ...$site->content,
            'headline' => 'Staging headline check',
        ],
    ])->save();

    $host = SiteStaging::hostname($site);

    $this->get('https://'.$host)
        ->assertOk()
        ->assertSee('Staging headline check')
        ->assertSee('<link rel="canonical" href="https://'.$host.'/">', escape: false)
        ->assertSee('noindex, nofollow', escape: false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeaderMissing('Set-Cookie')
        ->assertDontSee('<div class="claim-bar"', escape: false);
});

test('unpublished staging hostnames are not found', function () {
    [$site] = stagingSite();

    $this->get('https://'.SiteStaging::hostname($site))->assertNotFound();
});

test('staging can be removed and promoted to production', function () {
    [$site, $user, $args] = stagingSite();
    SiteDomain::factory()->verified()->for($site)->create(['hostname' => 'live.example.com']);

    $this->actingAs($user)->post(route('sites.staging.store', $args));
    expect($site->fresh()->isStagingPublished())->toBeTrue();

    $this->actingAs($user)->post(route('sites.staging.promote', $args))->assertRedirect();
    expect($site->fresh()->isPublished())->toBeTrue()
        ->and($site->fresh()->isStagingPublished())->toBeTrue();

    $this->actingAs($user)->delete(route('sites.staging.destroy', $args))->assertRedirect();
    expect($site->fresh()->isStagingPublished())->toBeFalse()
        ->and($site->fresh()->isPublished())->toBeTrue();
});

test('promote to live requires a verified domain and passing quality', function () {
    [$site, $user, $args] = stagingSite(['phone' => null, 'email' => null]);

    $this->actingAs($user)->post(route('sites.staging.store', $args));

    $this->actingAs($user)->post(route('sites.staging.promote', $args))
        ->assertSessionHasErrors('publish');

    SiteDomain::factory()->verified()->for($site)->create();

    $this->actingAs($user)->post(route('sites.staging.promote', $args))
        ->assertSessionHasErrors(['publish' => 'Fix this before publishing: No phone or email, visitors cannot get in touch.']);
});

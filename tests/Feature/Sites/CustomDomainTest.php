<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Services\Domains\DnsLookup;
use App\Services\Domains\DomainVerifier;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: Site, 1: User, 2: array{current_team: string, site: int}}
 */
function domainSite(array $leadAttributes = []): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['phone' => '555-0100', ...$leadAttributes]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    return [$site, $user, ['current_team' => $user->currentTeam->slug, 'site' => $site->id]];
}

/**
 * Answer DNS lookups from the given records instead of the network.
 *
 * @param  array<string, list<string>>  $txt
 * @param  array<string, list<string>>  $a
 * @param  array<string, list<string>>  $cname
 */
function fakeDns(array $txt = [], array $a = [], array $cname = []): void
{
    app()->instance(DnsLookup::class, new class($txt, $a, $cname) extends DnsLookup
    {
        /**
         * @param  array<string, list<string>>  $txtRecords
         * @param  array<string, list<string>>  $aRecords
         * @param  array<string, list<string>>  $cnameRecords
         */
        public function __construct(protected array $txtRecords, protected array $aRecords, protected array $cnameRecords) {}

        public function txt(string $host): array
        {
            return $this->txtRecords[$host] ?? [];
        }

        public function a(string $host): array
        {
            return $this->aRecords[$host] ?? [];
        }

        public function cname(string $host): array
        {
            return $this->cnameRecords[$host] ?? [];
        }
    });
}

test('a domain can be added and starts unverified', function () {
    [$site, $user, $args] = domainSite();

    $this->actingAs($user)->post(route('sites.domains.store', $args), ['hostname' => 'https://WWW.Pros-Plumbing.com/about?x=1'])->assertRedirect();

    $domain = $site->domains()->sole();

    expect($domain->hostname)->toBe('www.pros-plumbing.com')
        ->and($domain->isVerified())->toBeFalse()
        ->and(strlen($domain->verification_token))->toBe(32);
});

test('invalid domains are rejected', function (string $hostname) {
    config(['app.url' => 'https://app.example.org']);
    [, $user, $args] = domainSite();

    $this->actingAs($user)->post(route('sites.domains.store', $args), ['hostname' => $hostname])->assertSessionHasErrors('hostname');
})->with([
    'no dot' => 'localhost',
    'not a domain' => 'not a domain',
    'an ip address' => '192.168.1.10',
    'reserved ending' => 'shop.test',
    'the dashboard' => 'app.example.org',
    'under the dashboard' => 'clients.app.example.org',
    'empty' => '',
]);

test('a domain cannot be added twice or beyond the limit', function () {
    [$site, $user, $args] = domainSite();
    SiteDomain::factory()->for($site)->create(['hostname' => 'one.example.com']);

    $this->actingAs($user)->post(route('sites.domains.store', $args), ['hostname' => 'one.example.com'])->assertSessionHasErrors('hostname');

    SiteDomain::factory()->count(4)->for($site)->create();

    $this->actingAs($user)->post(route('sites.domains.store', $args), ['hostname' => 'six.example.com'])->assertSessionHasErrors('hostname');
});

test('only the owning team can manage domains', function () {
    [$site, $user, $args] = domainSite();
    [, $other] = domainSite();
    $domain = SiteDomain::factory()->for($site)->create();

    $this->actingAs($other)->post(route('sites.domains.store', ['current_team' => $other->currentTeam->slug, 'site' => $site->id]), ['hostname' => 'x.example.com'])->assertNotFound();
    $this->actingAs($other)->delete(route('sites.domains.destroy', ['current_team' => $other->currentTeam->slug, 'site' => $site->id, 'domain' => $domain->id]))->assertNotFound();
});

test('guests cannot add domains', function () {
    [, , $args] = domainSite();

    $this->post(route('sites.domains.store', $args), ['hostname' => 'x.example.com'])->assertRedirect(route('login'));
});

test('a domain is verified by its ownership record', function () {
    [$site, $user, $args] = domainSite();
    $domain = SiteDomain::factory()->for($site)->create(['hostname' => 'pros.example.com']);
    fakeDns(txt: ['_sitemap-verify.pros.example.com' => ['v=other', $domain->verification_token]]);

    $this->actingAs($user)->post(route('sites.domains.verify', [...$args, 'domain' => $domain->id]))->assertRedirect();

    expect($domain->fresh()->isVerified())->toBeTrue()
        ->and($domain->fresh()->verified_hostname)->toBe('pros.example.com')
        ->and($domain->fresh()->check_error)->toBeNull();
});

test('a domain is verified when it already points at the app', function (array $dns) {
    config(['sites.cname_target' => 'edge.sitemap.app', 'sites.server_ip' => '203.0.113.7']);
    [$site, $user, $args] = domainSite();
    $domain = SiteDomain::factory()->for($site)->create(['hostname' => 'pros.example.com']);
    fakeDns(...$dns);

    $this->actingAs($user)->post(route('sites.domains.verify', [...$args, 'domain' => $domain->id]));

    expect($domain->fresh()->isVerified())->toBeTrue();
})->with([
    'a CNAME' => [['cname' => ['pros.example.com' => ['edge.sitemap.app']]]],
    'an A record' => [['a' => ['pros.example.com' => ['203.0.113.7']]]],
]);

test('a domain without the right records stays unverified with a reason', function () {
    config(['sites.cname_target' => 'edge.sitemap.app', 'sites.server_ip' => '203.0.113.7']);
    [$site, $user, $args] = domainSite();
    $domain = SiteDomain::factory()->for($site)->create(['hostname' => 'pros.example.com']);
    fakeDns(txt: ['_sitemap-verify.pros.example.com' => ['wrong-token']], a: ['pros.example.com' => ['198.51.100.9']]);

    $this->actingAs($user)->post(route('sites.domains.verify', [...$args, 'domain' => $domain->id]));

    $domain->refresh();

    expect($domain->isVerified())->toBeFalse()
        ->and($domain->check_error)->toContain('could not find')
        ->and($domain->last_checked_at)->not->toBeNull();
});

test('a domain already live on another website cannot be verified again', function () {
    [$site, $user, $args] = domainSite();
    [$otherSite] = domainSite();
    SiteDomain::factory()->verified()->for($otherSite)->create(['hostname' => 'pros.example.com']);
    $domain = SiteDomain::factory()->for($site)->create(['hostname' => 'pros.example.com']);
    fakeDns(txt: ['_sitemap-verify.pros.example.com' => [$domain->verification_token]]);

    $this->actingAs($user)->post(route('sites.domains.verify', [...$args, 'domain' => $domain->id]));

    expect($domain->fresh()->isVerified())->toBeFalse()
        ->and($domain->fresh()->check_error)->toContain('another website');
});

test('the dns instructions fit the kind of domain', function () {
    config(['sites.cname_target' => 'edge.sitemap.app', 'sites.server_ip' => '203.0.113.7']);
    $verifier = app(DomainVerifier::class);
    $records = fn (string $host) => collect($verifier->instructions(SiteDomain::factory()->make(['hostname' => $host])))->pluck('value', 'type');

    expect($records('www.pros.com')->all())->toHaveKey('CNAME', 'edge.sitemap.app')
        ->and($records('pros.com')->all())->toHaveKey('A', '203.0.113.7')
        ->and($records('pros.co.uk')->all())->toHaveKey('A', '203.0.113.7')
        ->and($records('www.pros.co.uk')->all())->toHaveKey('CNAME', 'edge.sitemap.app')
        ->and($records('pros.com')->all())->toHaveKey('TXT');

    config(['sites.server_ip' => null]);

    expect($records('pros.com')->all())->toHaveKey('ALIAS', 'edge.sitemap.app');
});

test('a domain can be removed', function () {
    [$site, $user, $args] = domainSite();
    $domain = SiteDomain::factory()->for($site)->create();

    $this->actingAs($user)->delete(route('sites.domains.destroy', [...$args, 'domain' => $domain->id]))->assertRedirect();

    expect(SiteDomain::count())->toBe(0);
});

test('a site cannot be published without a verified domain', function () {
    [$site, $user, $args] = domainSite();
    SiteDomain::factory()->for($site)->create();

    $this->actingAs($user)->post(route('sites.publish.store', $args))->assertSessionHasErrors('publish');

    expect($site->fresh()->isPublished())->toBeFalse();
});

test('a site with a failing quality check cannot be published', function () {
    [$site, $user, $args] = domainSite(['phone' => null, 'email' => null]);
    SiteDomain::factory()->verified()->for($site)->create();

    $this->actingAs($user)->post(route('sites.publish.store', $args))
        ->assertSessionHasErrors(['publish' => 'Fix this before publishing: No phone or email, visitors cannot get in touch.']);
});

test('a site can be published and taken offline again', function () {
    [$site, $user, $args] = domainSite();
    SiteDomain::factory()->verified()->for($site)->create(['hostname' => 'pros.example.com']);

    $this->actingAs($user)->post(route('sites.publish.store', $args))->assertRedirect()->assertSessionHasNoErrors();
    expect($site->fresh()->isPublished())->toBeTrue();

    $this->actingAs($user)->delete(route('sites.publish.destroy', $args))->assertRedirect();
    expect($site->fresh()->isPublished())->toBeFalse();
});

test('the certificate check only approves live domains', function () {
    config(['sites.tls_ask_token' => 'secret-token']);
    [$site] = domainSite();
    $site->forceFill(['published_at' => now()])->save();
    SiteDomain::factory()->verified()->for($site)->create(['hostname' => 'pros.example.com']);
    SiteDomain::factory()->for($site)->create(['hostname' => 'pending.example.com']);

    $ask = fn (string $domain, string $token = 'secret-token') => $this->get(route('domains.allowed', ['domain' => $domain, 'token' => $token]));

    $ask('pros.example.com')->assertOk();
    $ask('PROS.example.com')->assertOk();
    $ask('pending.example.com')->assertNotFound();
    $ask('stranger.example.com')->assertNotFound();
    $ask('pros.example.com', 'wrong')->assertNotFound();

    $site->forceFill(['published_at' => null])->save();
    $ask('pros.example.com')->assertNotFound();
});

test('the certificate check is off until a token is configured', function () {
    config(['sites.tls_ask_token' => null]);

    $this->get(route('domains.allowed', ['domain' => 'pros.example.com', 'token' => '']))->assertNotFound();
});

test('the editor receives the publishing status and dns records', function () {
    config(['sites.cname_target' => 'edge.sitemap.app']);
    [$site, $user, $args] = domainSite();
    SiteDomain::factory()->for($site)->create(['hostname' => 'www.pros.example.com']);

    $this->actingAs($user)->get(route('sites.edit', $args))
        ->assertInertia(fn (Assert $page) => $page
            ->where('publishing.isPublished', false)
            ->where('publishing.canPublish', false)
            ->where('publishing.dnsConfigured', true)
            ->where('publishing.domains.0.hostname', 'www.pros.example.com')
            ->where('publishing.domains.0.records.1.type', 'CNAME'));
});

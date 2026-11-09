<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Services\Cloudflare\SyncCloudflareHostname;
use App\Services\Domains\DnsLookup;
use App\Services\Domains\DomainVerifier;
use Illuminate\Support\Facades\Http;

function cloudflareSite(): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'phone' => '555-0100',
        'email' => 'owner@example.com',
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    return [$site, $user];
}

test('cloudflare sync is skipped when credentials are missing', function () {
    config(['services.cloudflare.api_token' => null, 'services.cloudflare.zone_id' => null]);

    Http::fake();

    [$site] = cloudflareSite();
    $domain = SiteDomain::factory()->verified()->for($site)->create(['hostname' => 'www.pros.example.com']);

    app(SyncCloudflareHostname::class)->syncSiteDomain($domain);

    Http::assertNothingSent();
    expect($domain->fresh()->cloudflare_hostname_id)->toBeNull();
});

test('verifying a domain registers it with cloudflare for saas', function () {
    config([
        'services.cloudflare.api_token' => 'cf-token',
        'services.cloudflare.zone_id' => 'zone-123',
        'sites.cname_target' => 'sites.mapmaticapp.com',
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123/custom_hostnames' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'ch-1111-2222-3333-4444-555555555555',
                'hostname' => 'www.pros.example.com',
                'status' => 'pending',
                'ssl' => ['status' => 'pending_validation'],
            ],
        ], 200),
    ]);

    [$site, $user] = cloudflareSite();
    $domain = SiteDomain::factory()->for($site)->create([
        'hostname' => 'www.pros.example.com',
        'verification_token' => 'token-abc',
    ]);

    app()->instance(DnsLookup::class, new class(['_sitemap-verify.www.pros.example.com' => ['token-abc']], [], []) extends DnsLookup
    {
        public function __construct(protected array $txtRecords, protected array $aRecords, protected array $cnameRecords) {}

        public function txt(string $name): array
        {
            return $this->txtRecords[$name] ?? [];
        }

        public function a(string $hostname): array
        {
            return $this->aRecords[$hostname] ?? [];
        }

        public function cname(string $hostname): array
        {
            return $this->cnameRecords[$hostname] ?? [];
        }
    });

    $this->actingAs($user)
        ->post(route('sites.domains.verify', [
            'current_team' => $user->currentTeam->slug,
            'site' => $site->id,
            'domain' => $domain->id,
        ]))
        ->assertRedirect();

    $domain->refresh();

    expect($domain)
        ->verified_at->not->toBeNull()
        ->cloudflare_hostname_id->toBe('ch-1111-2222-3333-4444-555555555555')
        ->cloudflare_status->toBe('pending')
        ->cloudflare_ssl_status->toBe('pending_validation')
        ->check_error->toContain('HTTPS is still activating');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-123/custom_hostnames'
        && $request['hostname'] === 'www.pros.example.com');
});

test('removing a domain deletes the cloudflare custom hostname', function () {
    config([
        'services.cloudflare.api_token' => 'cf-token',
        'services.cloudflare.zone_id' => 'zone-123',
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123/custom_hostnames/*' => Http::response(['success' => true], 200),
    ]);

    [$site, $user] = cloudflareSite();
    $domain = SiteDomain::factory()->verified()->for($site)->create([
        'hostname' => 'www.pros.example.com',
        'cloudflare_hostname_id' => 'ch-delete-me-0000-0000-000000000000',
    ]);

    $this->actingAs($user)
        ->delete(route('sites.domains.destroy', [
            'current_team' => $user->currentTeam->slug,
            'site' => $site->id,
            'domain' => $domain->id,
        ]))
        ->assertRedirect();

    expect(SiteDomain::query()->whereKey($domain->id)->exists())->toBeFalse();
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/custom_hostnames/ch-delete-me-0000-0000-000000000000'));
});

test('rechecking a verified domain refreshes cloudflare ssl status', function () {
    config([
        'services.cloudflare.api_token' => 'cf-token',
        'services.cloudflare.zone_id' => 'zone-123',
        'sites.cname_target' => 'sites.mapmaticapp.com',
    ]);

    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123/custom_hostnames/ch-1111-2222-3333-4444-555555555555' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'ch-1111-2222-3333-4444-555555555555',
                'hostname' => 'www.pros.example.com',
                'status' => 'active',
                'ssl' => ['status' => 'active'],
            ],
        ], 200),
    ]);

    [$site] = cloudflareSite();
    $domain = SiteDomain::factory()->verified()->for($site)->create([
        'hostname' => 'www.pros.example.com',
        'verification_token' => 'token-abc',
        'cloudflare_hostname_id' => 'ch-1111-2222-3333-4444-555555555555',
        'cloudflare_status' => 'pending',
        'cloudflare_ssl_status' => 'pending_validation',
    ]);

    app()->instance(DnsLookup::class, new class(['_sitemap-verify.www.pros.example.com' => ['token-abc']], [], ['www.pros.example.com' => ['sites.mapmaticapp.com']]) extends DnsLookup
    {
        public function __construct(protected array $txtRecords, protected array $aRecords, protected array $cnameRecords) {}

        public function txt(string $name): array
        {
            return $this->txtRecords[$name] ?? [];
        }

        public function a(string $hostname): array
        {
            return $this->aRecords[$hostname] ?? [];
        }

        public function cname(string $hostname): array
        {
            return $this->cnameRecords[$hostname] ?? [];
        }
    });

    expect(app(DomainVerifier::class)->verify($domain))->toBeTrue();

    expect($domain->fresh())
        ->cloudflare_status->toBe('active')
        ->cloudflare_ssl_status->toBe('active')
        ->check_error->toBeNull()
        ->and($domain->fresh()->sslIsActive())->toBeTrue();
});

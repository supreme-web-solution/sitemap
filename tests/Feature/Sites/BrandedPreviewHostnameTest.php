<?php

use App\Models\Lead;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Services\Domains\DnsLookup;
use App\Services\Preview\PreviewLink;
use Illuminate\Support\Facades\Http;

test('the branding page shows the verified preview origin and hides setup as the main focus', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill([
        'plan_id' => Plan::factory()->withAllFeatures()->create()->id,
        'preview_hostname' => 'previews.agency.test',
        'preview_hostname_verified_at' => now(),
        'preview_hostname_token' => 'token',
    ])->save();

    $this->actingAs($owner)
        ->get(route('branding.edit', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('branding/Edit')
            ->where('previewDomain.verified', true)
            ->where('previewDomain.origin', 'https://previews.agency.test')
            ->where('previewDomain.hostname', 'previews.agency.test'));
});

test('preview links use the verified agency hostname', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill([
        'preview_hostname' => 'previews.agency.test',
        'preview_hostname_verified_at' => now(),
    ])->save();

    $lead = Lead::factory()->for($team)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    expect(PreviewLink::page($site))->toBe('https://previews.agency.test/p/'.$site->slug);
});

test('a verified preview hostname serves preview pages', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill([
        'preview_hostname' => 'previews.agency.test',
        'preview_hostname_verified_at' => now(),
    ])->save();

    $lead = Lead::factory()->for($team)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->get('https://previews.agency.test/p/'.$site->slug)
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('index, follow, max-image-preview:large', escape: false);
});

test('another teams site cannot be opened on a branded preview host', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill([
        'preview_hostname' => 'previews.agency.test',
        'preview_hostname_verified_at' => now(),
    ])->save();

    $otherSite = Site::factory()->create();

    $this->get('https://previews.agency.test/p/'.$otherSite->slug)->assertNotFound();
});

test('branded preview host allows tls ask when verified', function () {
    config(['sites.tls_ask_token' => 'secret']);

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill([
        'preview_hostname' => 'previews.agency.test',
        'preview_hostname_verified_at' => now(),
    ])->save();

    $this->get(route('domains.allowed', ['domain' => 'previews.agency.test', 'token' => 'secret']))
        ->assertOk()
        ->assertSee('ok');
});

test('team admins can save and verify a preview hostname on white label plans', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill(['plan_id' => Plan::factory()->withAllFeatures()->create()->id])->save();

    $this->actingAs($owner)
        ->post(route('branding.preview-domain.store', ['current_team' => $team->slug]), [
            'hostname' => 'previews.example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $team->refresh();

    expect($team->preview_hostname)->toBe('previews.example.com')
        ->and($team->preview_hostname_token)->not->toBeEmpty();

    app()->instance(DnsLookup::class, new class(['_sitemap-verify.previews.example.com' => [$team->preview_hostname_token]], [], []) extends DnsLookup
    {
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

    $this->actingAs($owner)
        ->post(route('branding.preview-domain.verify', ['current_team' => $team->slug]))
        ->assertRedirect();

    expect($team->fresh()->hasVerifiedPreviewHostname())->toBeTrue();
});

test('preview hostname requires white label', function () {
    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill(['plan_id' => Plan::factory()->create(['features' => []])->id])->save();

    $this->actingAs($owner)
        ->post(route('branding.preview-domain.store', ['current_team' => $team->slug]), [
            'hostname' => 'previews.example.com',
        ])
        ->assertRedirect();

    expect($team->fresh()->preview_hostname)->toBeNull();
});

test('a preview hostname already used by another team is rejected with a clear error', function () {
    $other = User::factory()->create([
        'name' => 'Sam Smith',
        'email' => 'sam@smith-agency.test',
    ]);
    $other->currentTeam->forceFill([
        'name' => "smith's Team",
        'preview_hostname' => 'test.mapmaticapp.com',
        'preview_hostname_token' => 'taken',
    ])->save();

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill(['plan_id' => Plan::factory()->withAllFeatures()->create()->id])->save();

    $this->actingAs($owner)
        ->from(route('branding.edit', ['current_team' => $team->slug]))
        ->post(route('branding.preview-domain.store', ['current_team' => $team->slug]), [
            'hostname' => 'test.mapmaticapp.com',
        ])
        ->assertRedirect(route('branding.edit', ['current_team' => $team->slug]))
        ->assertSessionHasErrors([
            'hostname' => "That domain is already used as the preview domain for smith's Team (Sam Smith <sam@smith-agency.test>).",
        ]);

    expect($team->fresh()->preview_hostname)->toBeNull();
});

test('a preview hostname already used by a verified site domain is rejected', function () {
    SiteDomain::factory()->verified()->create(['hostname' => 'client.example.com']);

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill(['plan_id' => Plan::factory()->withAllFeatures()->create()->id])->save();

    $this->actingAs($owner)
        ->from(route('branding.edit', ['current_team' => $team->slug]))
        ->post(route('branding.preview-domain.store', ['current_team' => $team->slug]), [
            'hostname' => 'client.example.com',
        ])
        ->assertRedirect(route('branding.edit', ['current_team' => $team->slug]))
        ->assertSessionHasErrors([
            'hostname' => 'That domain is already connected to a published website.',
        ]);

    expect($team->fresh()->preview_hostname)->toBeNull();
});

test('removing a preview domain clears local fields and deletes it from Cloudflare', function () {
    config([
        'services.cloudflare.api_token' => 'cf-token',
        'services.cloudflare.zone_id' => 'zone-123',
    ]);

    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill([
        'plan_id' => Plan::factory()->withAllFeatures()->create()->id,
        'preview_hostname' => 'test.mapmaticapp.com',
        'preview_hostname_token' => 'token',
        'preview_hostname_verified_at' => now(),
        'preview_cloudflare_hostname_id' => 'ch-preview-1',
        'preview_cloudflare_status' => 'active',
        'preview_cloudflare_ssl_status' => 'active',
    ])->save();

    $this->actingAs($owner)
        ->delete(route('branding.preview-domain.destroy', ['current_team' => $team->slug]))
        ->assertRedirect();

    expect($team->fresh())
        ->preview_hostname->toBeNull()
        ->preview_hostname_token->toBeNull()
        ->preview_hostname_verified_at->toBeNull()
        ->preview_cloudflare_hostname_id->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/custom_hostnames/ch-preview-1'));
});

test('removing a preview domain still deletes Cloudflare when only the hostname is known', function () {
    config([
        'services.cloudflare.api_token' => 'cf-token',
        'services.cloudflare.zone_id' => 'zone-123',
    ]);

    Http::fake([
        'api.cloudflare.com/*/custom_hostnames?*' => Http::response([
            'success' => true,
            'result' => [[
                'id' => 'ch-found-by-name',
                'hostname' => 'orphan-preview.mapmaticapp.com',
                'status' => 'active',
                'ssl' => ['status' => 'active'],
            ]],
        ], 200),
        'api.cloudflare.com/*/custom_hostnames/ch-found-by-name' => Http::response(['success' => true], 200),
    ]);

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill([
        'plan_id' => Plan::factory()->withAllFeatures()->create()->id,
        'preview_hostname' => 'orphan-preview.mapmaticapp.com',
        'preview_hostname_token' => 'token',
        'preview_cloudflare_hostname_id' => null,
    ])->save();

    $this->actingAs($owner)
        ->delete(route('branding.preview-domain.destroy', ['current_team' => $team->slug]))
        ->assertRedirect();

    expect($team->fresh()->preview_hostname)->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/custom_hostnames/ch-found-by-name'));
});

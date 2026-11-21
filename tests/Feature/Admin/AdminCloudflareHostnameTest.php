<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'admin.emails' => ['boss@example.com'],
        'services.cloudflare.api_token' => 'cf-token',
        'services.cloudflare.zone_id' => 'zone-123',
    ]);
    $this->admin = User::factory()->create(['email' => 'boss@example.com']);
});

test('admins can list cloudflare custom hostnames', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'id' => 'ch-orphan',
                    'hostname' => 'www.pros-plumbing.com',
                    'status' => 'pending',
                    'ssl' => ['status' => 'pending_validation'],
                    'verification_errors' => ['custom hostname does not CNAME to this zone.'],
                    'ownership_verification' => [
                        'name' => '_cf-custom-hostname.www.pros-plumbing.com',
                        'value' => 'abc',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.cloudflare-hostnames'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CloudflareHostnames')
            ->where('enabled', true)
            ->has('hostnames', 1)
            ->where('hostnames.0.hostname', 'www.pros-plumbing.com')
            ->where('hostnames.0.kind', 'orphan')
            ->where('hostnames.0.linked', null));
});

test('admins see site hosting vs brand preview and the account owner', function () {
    $siteOwner = User::factory()->create([
        'name' => 'Site Owner',
        'email' => 'site-owner@example.com',
    ]);
    $previewOwner = User::factory()->create([
        'name' => 'Preview Owner',
        'email' => 'preview-owner@example.com',
    ]);

    $site = Site::factory()->create([
        'lead_id' => Lead::factory()->create(['team_id' => $siteOwner->currentTeam->id]),
        'team_id' => $siteOwner->currentTeam->id,
    ]);
    SiteDomain::factory()->for($site)->create([
        'hostname' => 'www.client-site.com',
        'cloudflare_hostname_id' => 'ch-site',
    ]);

    $previewOwner->currentTeam->forceFill([
        'preview_hostname' => 'preview.preview-owner.mapmaticapp.com',
        'preview_cloudflare_hostname_id' => 'ch-preview',
    ])->save();

    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'id' => 'ch-site',
                    'hostname' => 'www.client-site.com',
                    'status' => 'active',
                    'ssl' => ['status' => 'active'],
                    'verification_errors' => [],
                    'ownership_verification' => null,
                ],
                [
                    'id' => 'ch-preview',
                    'hostname' => 'preview.preview-owner.mapmaticapp.com',
                    'status' => 'active',
                    'ssl' => ['status' => 'active'],
                    'verification_errors' => [],
                    'ownership_verification' => null,
                ],
                [
                    'id' => 'ch-orphan',
                    'hostname' => 'leftover.example.com',
                    'status' => 'pending',
                    'ssl' => ['status' => 'pending_validation'],
                    'verification_errors' => [],
                    'ownership_verification' => null,
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.cloudflare-hostnames'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CloudflareHostnames')
            ->has('hostnames', 3)
            ->where('hostnames.0.kind', 'site_domain')
            ->where('hostnames.0.linked.type', 'site_domain')
            ->where('hostnames.0.linked.account.name', 'Site Owner')
            ->where('hostnames.0.linked.account.email', 'site-owner@example.com')
            ->where('hostnames.1.kind', 'preview')
            ->where('hostnames.1.linked.type', 'preview')
            ->where('hostnames.1.linked.account.name', 'Preview Owner')
            ->where('hostnames.1.linked.account.email', 'preview-owner@example.com')
            ->where('hostnames.2.kind', 'orphan')
            ->where('hostnames.2.linked', null));
});

test('non-admins cannot open cloudflare hostnames', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('admin.cloudflare-hostnames'))->assertForbidden();
});

test('admins can delete a cloudflare hostname and clear local links', function () {
    Http::fake([
        'api.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $domain = SiteDomain::factory()->create([
        'hostname' => 'www.pros-plumbing.com',
        'cloudflare_hostname_id' => 'ch-delete-me',
        'cloudflare_status' => 'pending',
        'cloudflare_ssl_status' => 'pending_validation',
        'cloudflare_ownership_txt_name' => '_cf-custom-hostname.www.pros-plumbing.com',
        'cloudflare_ownership_txt_value' => 'abc',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.cloudflare-hostnames.destroy', ['hostnameId' => 'ch-delete-me']))
        ->assertRedirect();

    expect($domain->fresh())
        ->cloudflare_hostname_id->toBeNull()
        ->cloudflare_status->toBeNull()
        ->cloudflare_ownership_txt_value->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/custom_hostnames/ch-delete-me'));
});

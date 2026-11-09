<?php

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
            ->where('hostnames.0.linked', null));
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

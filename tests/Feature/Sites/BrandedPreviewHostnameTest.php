<?php

use App\Models\Lead;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use App\Services\Domains\DnsLookup;
use App\Services\Preview\PreviewLink;

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
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
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

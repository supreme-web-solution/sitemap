<?php

use App\Jobs\CheckSiteUptime;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Services\Audit\SafeHttpFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('uptime checks mark the site down when https handshake fails instead of exploding', function () {
    Http::preventStrayRequests();

    $owner = User::factory()->create();
    $site = Site::factory()->create(['team_id' => $owner->currentTeam->id]);
    SiteDomain::factory()->verified()->create([
        'site_id' => $site->id,
        'hostname' => 'broken-ssl.example.com',
    ]);

    $fetcher = Mockery::mock(SafeHttpFetcher::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->with('https://broken-ssl.example.com')
        ->andThrow(new ConnectionException(
            'cURL error 35: SSL routines::sslv3 alert handshake failure',
        ));

    (new CheckSiteUptime($site))->handle($fetcher);

    expect($site->fresh())
        ->uptime_ok->toBeFalse()
        ->last_uptime_at->not->toBeNull();
});

test('preview uptime uses the app host even when a branded preview hostname is set', function () {
    config(['app.url' => 'https://app.example.com']);

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->forceFill([
        'preview_hostname' => 'previews.agency.test',
        'preview_hostname_verified_at' => now(),
    ])->save();

    $site = Site::factory()->create(['team_id' => $team->id]);

    $job = new CheckSiteUptime($site);
    $method = new ReflectionMethod($job, 'url');

    expect($method->invoke($job))->toStartWith('https://app.example.com/p/'.$site->slug)
        ->and($method->invoke($job))->not->toContain('previews.agency.test');
});

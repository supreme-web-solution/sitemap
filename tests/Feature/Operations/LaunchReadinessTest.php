<?php

use App\Models\User;
use App\Services\Operations\LaunchReadinessChecker;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('ops verify command prints a report and succeeds in local testing', function () {
    $this->artisan('ops:verify')
        ->assertSuccessful();
});

test('ops verify fails in production mode when tls token is missing', function () {
    config(['sites.tls_ask_token' => '']);

    $report = app(LaunchReadinessChecker::class)->run(live: false, production: true);

    expect($report->ok())->toBeFalse()
        ->and(collect($report->checks)->firstWhere('id', 'tls_token')?->status)->toBe('fail');
});

test('ops verify live mode probes the tls gate', function () {
    config([
        'app.url' => 'https://app.example.test',
        'sites.tls_ask_token' => 'secret-token',
    ]);

    Http::fake([
        'https://app.example.test' => Http::response('', 302),
        'https://app.example.test/internal/domains/allowed*' => Http::response('', 404),
    ]);

    $report = app(LaunchReadinessChecker::class)->run(live: true, production: false);

    $tlsChecks = collect($report->checks)->where('id', 'tls_gate');

    expect($tlsChecks->every(fn ($check) => $check->status === 'pass'))->toBeTrue();
});

test('platform admins can open the launch readiness page', function () {
    config(['admin.emails' => ['boss@example.com']]);
    $admin = User::factory()->create(['email' => 'boss@example.com']);

    $this->actingAs($admin)
        ->get(route('admin.launch-readiness'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/LaunchReadiness')
            ->has('report.checks')
            ->has('report.summary')
            ->where('liveCommand', 'php artisan ops:verify --live'),
        );
});

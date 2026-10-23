<?php

use App\Enums\LeadActivityType;
use App\Enums\SiteGenerationStatus;
use App\Jobs\AuditLeadWebsite;
use App\Models\Lead;
use App\Models\User;
use App\Services\Audit\SafeHttpFetcher;
use App\Services\Audit\UnsafeUrlException;
use App\Services\Audit\WebsiteAuditor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('the fetcher refuses urls that point at private or unsafe destinations', function (string $url) {
    expect(fn () => app(SafeHttpFetcher::class)->validate($url))->toThrow(UnsafeUrlException::class);
})->with([
    'loopback' => 'http://127.0.0.1/admin',
    'private network' => 'http://10.0.0.5/',
    'link local metadata service' => 'http://169.254.169.254/latest/meta-data',
    'localhost name' => 'http://localhost/',
    'file scheme' => 'file:///etc/passwd',
    'ftp scheme' => 'ftp://example.com/file',
    'credentials in the url' => 'https://user:pass@93.184.216.34/',
    'non standard port' => 'http://93.184.216.34:8080/',
]);

test('the fetcher accepts a public ip address', function () {
    expect(app(SafeHttpFetcher::class)->validate('https://93.184.216.34/page'))
        ->toBe(['93.184.216.34', 443, '93.184.216.34']);
});

test('the fetcher does not follow a redirect into a private network', function () {
    Http::fake([
        '93.184.216.34/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest']),
    ]);

    expect(fn () => app(SafeHttpFetcher::class)->fetch('http://93.184.216.34/'))
        ->toThrow(UnsafeUrlException::class);
});

test('the auditor flags problems on a weak website and scores it low', function () {
    Http::fake(['93.184.216.34/*' => Http::response('<html><head></head><body><p>Hi</p></body></html>', 200)]);

    $audit = app(WebsiteAuditor::class)->audit('http://93.184.216.34/');

    $statuses = collect($audit['checks'])->pluck('status', 'key');

    expect($audit['error'])->toBeNull()
        ->and($audit['score'])->toBeLessThan(40)
        ->and($statuses['https'])->toBe('fail')
        ->and($statuses['viewport'])->toBe('fail')
        ->and($statuses['title'])->toBe('fail')
        ->and($statuses['contact'])->toBe('fail');
});

test('the auditor scores a well built website highly', function () {
    $html = <<<'HTML'
    <html><head>
      <title>Austin Pro Plumbing | Emergency plumber</title>
      <meta name="description" content="Fast, reliable plumbing in Austin. Call us for repairs, installs and emergency call-outs any time.">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta property="og:title" content="Austin Pro Plumbing">
    </head><body>
      <h1>Austin's plumber</h1>
      <img src="a.jpg" alt="A plumber at work">
      <a href="tel:+15125550192">Call us today</a>
    </body></html>
    HTML;

    Http::fake(['93.184.216.34/*' => Http::response($html, 200)]);

    $audit = app(WebsiteAuditor::class)->audit('https://93.184.216.34/');

    expect($audit['score'])->toBeGreaterThanOrEqual(90)
        ->and(collect($audit['checks'])->where('status', 'fail'))->toBeEmpty();
});

test('an unreachable website produces an error instead of an exception', function () {
    $audit = app(WebsiteAuditor::class)->audit('http://127.0.0.1/');

    expect($audit['error'])->not->toBeNull()
        ->and($audit['score'])->toBe(0);
});

test('a non success response is reported as an error', function () {
    Http::fake(['93.184.216.34/*' => Http::response('Not found', 404)]);

    expect(app(WebsiteAuditor::class)->audit('https://93.184.216.34/')['error'])->toContain('404');
});

test('the audit job stores the result, records activity and raises the opportunity score', function () {
    Http::fake(['93.184.216.34/*' => Http::response('<html><body>hi</body></html>', 200)]);

    $lead = Lead::factory()->create(['website' => 'https://93.184.216.34/']);
    $before = $lead->opportunity_score;

    (new AuditLeadWebsite($lead))->handle(app(WebsiteAuditor::class));

    $lead->refresh();

    expect($lead->website_audit['score'])->toBeLessThan(50)
        ->and($lead->website_audited_at)->not->toBeNull()
        ->and($lead->opportunity_score)->toBeGreaterThan($before)
        ->and($lead->activities()->where('type', LeadActivityType::WebsiteAudited)->exists())->toBeTrue();
});

test('an audit can be queued for a lead with a website only', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $withSite = Lead::factory()->for($team)->withWebsite()->create();
    $without = Lead::factory()->for($team)->create(['website' => null]);

    $this->actingAs($user)
        ->post(route('leads.audit.store', ['current_team' => $team->slug, 'lead' => $withSite->id]))
        ->assertRedirect();

    Queue::assertPushed(AuditLeadWebsite::class, fn ($job) => $job->lead->is($withSite));
    expect($withSite->fresh()->website_audit_status)->toBe(SiteGenerationStatus::Queued);

    $this->actingAs($user)
        ->post(route('leads.audit.store', ['current_team' => $team->slug, 'lead' => $without->id]))
        ->assertSessionHasErrors('website');

    Queue::assertPushed(AuditLeadWebsite::class, 1);
});

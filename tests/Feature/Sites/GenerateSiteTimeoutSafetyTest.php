<?php

use App\Actions\Sites\GenerateSite;
use App\Enums\LeadStatus;
use App\Enums\SiteGenerationStatus;
use App\Jobs\GenerateLeadSite;
use App\Models\Lead;
use App\Services\SiteBuilder\SiteBrandAssets;

test('site generation allows enough time for AI copy and brand images', function () {
    $job = new GenerateLeadSite(Lead::factory()->make());

    expect($job->timeout)->toBe(240)
        ->and($job->timeout)->toBeLessThan(300)
        ->and($job->uniqueFor)->toBeGreaterThanOrEqual($job->timeout);
});

test('a finished site is kept when brand image generation fails', function () {
    $this->mock(SiteBrandAssets::class, function ($mock) {
        $mock->shouldReceive('refresh')->once()->andThrow(new RuntimeException('Could not render OG image'));
    });

    $lead = Lead::factory()->create([
        'name' => 'Clarke Kent Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
        'site_generation_status' => SiteGenerationStatus::Queued,
    ]);

    $site = app(GenerateSite::class)->handle($lead->fresh());

    expect($site->exists)->toBeTrue()
        ->and($lead->fresh())
        ->status->toBe(LeadStatus::SiteGenerated)
        ->site_generation_status->toBe(SiteGenerationStatus::Idle)
        ->site_generation_error->toBeNull()
        ->and($lead->site()->exists())->toBeTrue();
});

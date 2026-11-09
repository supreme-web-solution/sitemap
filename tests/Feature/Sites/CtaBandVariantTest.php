<?php

use App\Models\Lead;
use App\Models\Site;
use App\Services\SiteBuilder\SiteRenderer;

test('the call-to-action band supports layout variants and a background photo slot', function () {
    $lead = Lead::factory()->create([
        'name' => 'Handyman Mike',
        'photo_paths' => ['teams/1/leads/1/van.jpg'],
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'variants' => ['cta' => 'photo'],
            'images' => ['cta_band' => 'teams/1/leads/1/cta.jpg'],
        ],
    ]);

    $html = app(SiteRenderer::class)->render($site, asPreview: true);

    expect($html)->toContain('cta-v-photo')
        ->and($html)->not->toContain('data-section="cta"')
        ->and($html)->toContain('cta-band__media')
        ->and($html)->toContain('Ready to get started?');
});

test('the editor can select a photo background layout for the cta band', function () {
    $lead = Lead::factory()->create();
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'variants' => ['cta' => 'photo'],
        ],
    ]);

    $html = app(SiteRenderer::class)->render($site, asPreview: true, editor: true);

    expect($html)->toContain('cta-v-photo')
        ->and($html)->toContain('data-edit-image="cta_band"')
        ->and($html)->toContain('Add a background photo');
});

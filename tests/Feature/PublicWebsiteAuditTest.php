<?php

use App\Models\Lead;

test('the public audit report is available with a valid token', function () {
    $lead = Lead::factory()->create([
        'website_audit_public_token' => 'audit-token-abc',
        'website_audit' => [
            'url' => 'https://example.test',
            'final_url' => 'https://example.test',
            'fetched_at' => now()->toISOString(),
            'status_code' => 200,
            'score' => 73,
            'error' => null,
            'checks' => [
                [
                    'key' => 'https',
                    'label' => 'Secure connection (HTTPS)',
                    'status' => 'pass',
                    'detail' => 'Served over HTTPS.',
                ],
            ],
        ],
    ]);

    $this->get(route('website-audit.public', $lead->website_audit_public_token))
        ->assertOk()
        ->assertSee('Website health score')
        ->assertSee('73')
        ->assertSee('Secure connection');
});

test('the public audit report cannot be opened without a token', function () {
    $this->get(route('website-audit.public', 'missing-token'))->assertNotFound();
});

test('the public audit report is not available when the last audit failed', function () {
    $lead = Lead::factory()->create([
        'website_audit_public_token' => 'audit-token-failed',
        'website_audit' => [
            'url' => 'https://example.test',
            'final_url' => null,
            'fetched_at' => now()->toISOString(),
            'status_code' => 403,
            'score' => 0,
            'error' => 'The website responded with HTTP 403.',
            'checks' => [],
        ],
    ]);

    $this->get(route('website-audit.public', $lead->website_audit_public_token))
        ->assertNotFound();

    expect($lead->websiteAuditPublicUrl())->toBeNull();
});

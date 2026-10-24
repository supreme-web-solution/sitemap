<?php

use App\Enums\LeadActivityType;
use App\Jobs\EnrichLeadFromWebsite;
use App\Models\Lead;
use App\Models\User;
use App\Services\Prospecting\WebsiteProfileEnricher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('team members can queue website enrichment', function () {
    Queue::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'website' => 'https://example.com',
    ]);

    $this->actingAs($user)
        ->post(route('leads.enrichment.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertRedirect();

    Queue::assertPushed(EnrichLeadFromWebsite::class);
});

test('enrichment fills missing email and description from the website html', function () {
    Http::fake([
        'https://example.com' => Http::response('<html><head><meta name="description" content="Family plumbing"><a href="mailto:hello@example.com">Email</a></head></html>'),
    ]);

    $lead = Lead::factory()->create([
        'website' => 'https://example.com',
        'email' => null,
        'description' => null,
    ]);

    (new EnrichLeadFromWebsite($lead))->handle(app(WebsiteProfileEnricher::class));

    $lead->refresh();

    expect($lead->email)->toBe('hello@example.com')
        ->and($lead->description)->toBe('Family plumbing')
        ->and($lead->activities()->where('type', LeadActivityType::ProfileEnriched)->exists())->toBeTrue();
});

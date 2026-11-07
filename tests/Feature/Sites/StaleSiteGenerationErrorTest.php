<?php

use App\Enums\SiteGenerationStatus;
use App\Jobs\GenerateLeadSite;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;

test('a failed site job does not keep an error when the lead already has a site', function () {
    $lead = Lead::factory()->create([
        'site_generation_status' => SiteGenerationStatus::Queued,
        'site_generation_error' => null,
    ]);
    Site::factory()->create(['lead_id' => $lead->id]);

    (new GenerateLeadSite($lead))->failed(new RuntimeException('Anthropic credit exhausted'));

    expect($lead->fresh())
        ->site_generation_status->toBe(SiteGenerationStatus::Idle)
        ->site_generation_error->toBeNull();
});

test('lead lists hide generation errors once a site exists', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'site_generation_status' => SiteGenerationStatus::Failed,
        'site_generation_error' => 'The website could not be generated. Please try again.',
    ]);
    Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.siteGenerationError', null)
            ->where('leads.data.0.siteGenerationStatus', 'idle')
            ->where('leads.data.0.site.id', $lead->site->id));
});

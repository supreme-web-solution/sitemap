<?php

use App\Enums\AutomationTrigger;
use App\Enums\LeadActivityType;
use App\Jobs\AuditLeadWebsite;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\User;
use App\Services\Automation\AgencyWorkflowEngine;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('command center page loads chat shell for team members', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('command-center.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('command-center/Index')
            ->has('messages')
            ->has('mode')
            ->has('employee'));
});

test('team automation settings can be saved from automations page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->put(route('automations.update', ['current_team' => $team->slug]), [
            'enabled' => true,
            'default_sequence_id' => null,
            'enroll_sequence_on_lead_created' => false,
            'audit_website_on_lead_created' => true,
            'queue_site_on_lead_created' => false,
            'draft_proposal_on_site_generated' => false,
            'default_package_ids' => [],
            'move_interested_when_preview_in_outreach' => true,
            'move_interested_on_proposal_sent' => true,
            'onboarding_on_invoice_paid' => true,
            'reaudit_websites_every_days' => 14,
        ])
        ->assertRedirect();

    expect($team->fresh()->automationSettings()->reauditWebsitesEveryDays)->toBe(14);
});

test('lead created automation queues website audit when enabled', function () {
    Queue::fake([AuditLeadWebsite::class]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->forceFill([
        'automation' => [
            'enabled' => true,
            'audit_website_on_lead_created' => true,
        ],
    ])->save();

    $lead = Lead::factory()->create([
        'team_id' => $team->id,
        'website' => 'https://example.com',
    ]);

    app(AgencyWorkflowEngine::class)->run($team, AutomationTrigger::LeadCreated, $lead);

    Queue::assertPushed(AuditLeadWebsite::class);
});

test('enrolling on new lead creates activity when sequence exists', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $sequence = FollowUpSequence::factory()->withSteps()->create(['team_id' => $team->id]);

    $team->forceFill([
        'automation' => [
            'enabled' => true,
            'default_sequence_id' => $sequence->id,
            'enroll_sequence_on_lead_created' => true,
        ],
    ])->save();

    $lead = Lead::factory()->create([
        'team_id' => $team->id,
        'email' => 'owner@example.com',
    ]);

    app(AgencyWorkflowEngine::class)->run($team, AutomationTrigger::LeadCreated, $lead);

    expect($lead->activities()->where('type', LeadActivityType::Workflow)->exists())->toBeTrue();
});

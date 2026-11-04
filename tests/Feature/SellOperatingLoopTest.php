<?php

use App\Actions\Sites\GenerateSite;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\ProposalStatus;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\Site;
use App\Models\User;
use App\Services\Outreach\DefaultEmailTemplates;
use App\Services\Outreach\DefaultFollowUpSequences;
use App\Services\Preview\PreviewLink;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('the sell operating loop connects build profile outreach enrollment and close', function () {
    Mail::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $slug = $team->slug;

    $lead = Lead::factory()->for($team)->create([
        'email' => 'owner@example.com',
        'status' => LeadStatus::New,
        'opportunity_score' => 90,
    ]);

    $site = app(GenerateSite::class)->handle($lead->fresh());

    expect($lead->fresh()->status)->toBe(LeadStatus::SiteGenerated)
        ->and($site->lead_id)->toBe($lead->id);

    app(DefaultEmailTemplates::class)->ensureFor($team);
    app(DefaultFollowUpSequences::class)->ensureFor($team);

    $this->actingAs($user)
        ->get(route('leads.edit', ['current_team' => $slug, 'lead' => $lead->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/Edit')
            ->where('mergeValues.preview_url', PreviewLink::page($site))
            ->has('emailTemplates')
            ->has('sequences', 1)
            ->where('suggestions', fn ($rows) => collect($rows)->contains('title', 'Send the preview')));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workToday.items', fn ($items) => collect($items)->contains(
                fn ($item) => $item['leadId'] === $lead->id && $item['kind'] === 'send_preview',
            )));

    $this->actingAs($user)
        ->post(route('leads.outreach.store', ['current_team' => $slug, 'lead' => $lead->id]), [
            'mode' => 'log',
            'subject' => 'Shared the preview link',
            'body' => 'Emailed the live preview.',
        ])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Contacted)
        ->and($lead->next_follow_up_at)->not->toBeNull()
        ->and($lead->activities()->where('type', LeadActivityType::MarkedContacted)->exists())->toBeTrue();

    $sequence = $team->followUpSequences()->first();

    $this->actingAs($user)
        ->post(route('leads.enrollments.store', ['current_team' => $slug, 'lead' => $lead->id]), [
            'sequence_id' => $sequence->id,
        ])
        ->assertRedirect();

    expect($lead->enrollments()->sole()->status)->toBe(EnrollmentStatus::Active);

    $proposal = Proposal::factory()->withDefaultItems()->create(['lead_id' => $lead->id]);

    $this->post(route('proposals.public.accept', $proposal->public_token), [
        'name' => 'Pat Owner',
        'agree' => '1',
    ])->assertRedirect();

    expect($lead->fresh()->status)->toBe(LeadStatus::Won)
        ->and($proposal->fresh()->status)->toBe(ProposalStatus::Accepted)
        ->and(Invoice::where('lead_id', $lead->id)->exists())->toBeTrue();
});

test('leads index and board share the same pipeline stages for sell navigation', function () {
    $user = User::factory()->create();
    $slug = $user->currentTeam->slug;

    Lead::factory()->for($user->currentTeam)->create(['status' => LeadStatus::SiteGenerated]);

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $slug, 'status' => 'site_generated']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'site_generated')
            ->has('leads.data', 1));

    $this->actingAs($user)
        ->get(route('leads.board', ['current_team' => $slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.1.status', LeadStatus::SiteGenerated->value));
});

test('creating a site moves an idle lead onto the site generated stage', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);

    app(GenerateSite::class)->handle($lead->fresh());

    expect($lead->fresh()->status)->toBe(LeadStatus::SiteGenerated)
        ->and(Site::where('lead_id', $lead->id)->exists())->toBeTrue();
});

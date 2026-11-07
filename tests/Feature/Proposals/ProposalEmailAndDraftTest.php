<?php

use App\Ai\Agents\ProposalCopyDesigner;
use App\Enums\ProposalStatus;
use App\Mail\ProposalEmail;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('proposal emails use a designed html template', function () {
    $proposal = Proposal::factory()->withDefaultItems()->create([
        'title' => 'Website and hosting proposal',
        'intro' => 'Here is a clear plan for your new site.',
    ])->load(['lead', 'team', 'items']);

    $mailable = new ProposalEmail($proposal, 'William Victor', 'agency@example.com');

    $mailable->assertHasSubject('Proposal from '.$proposal->team->brand()['name'].': Website and hosting proposal');
    $mailable->assertSeeInHtml('Your proposal is ready');
    $mailable->assertSeeInHtml('View the proposal');
    $mailable->assertSeeInHtml($proposal->publicUrl());
    $mailable->assertSeeInText('View the proposal');
});

test('send to me emails a preview without marking the proposal sent', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'owner@example.com']);
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => 'client@example.com']);
    $proposal = Proposal::factory()->withDefaultItems()->create([
        'lead_id' => $lead->id,
        'status' => ProposalStatus::Draft,
        'sent_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('proposals.preview-email', [
            'current_team' => $user->currentTeam->slug,
            'proposal' => $proposal->id,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Mail::assertQueued(ProposalEmail::class, fn (ProposalEmail $mail) => $mail->hasTo('owner@example.com') && $mail->isPreview);
    expect($proposal->fresh()->status)->toBe(ProposalStatus::Draft)
        ->and($proposal->fresh()->sent_at)->toBeNull();
});

test('ai can draft introduction and terms for a proposal', function () {
    config(['services.anthropic.key' => 'test-key']);

    ProposalCopyDesigner::fake([
        [
            'intro' => 'Thanks for chatting about your plumbing business in Austin. Here is a practical website plan.',
            'terms' => "Payment is due on acceptance.\nDelivery starts after payment clears.\nExtra scope is quoted separately.",
        ],
    ]);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['name' => 'Blue Dragon Plumbing, LLC', 'city' => 'Austin']);

    $this->actingAs($user)
        ->postJson(route('proposals.draft-copy', ['current_team' => $user->currentTeam->slug]), [
            'lead_id' => $lead->id,
            'title' => 'Website and hosting proposal',
            'items' => [
                ['description' => 'Website build', 'quantity' => 1, 'unit_price' => 1500, 'interval' => null],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('intro', 'Thanks for chatting about your plumbing business in Austin. Here is a practical website plan.')
        ->assertJsonPath('terms', "Payment is due on acceptance.\nDelivery starts after payment clears.\nExtra scope is quoted separately.");
});

test('the proposals index includes an open action path', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $proposal = Proposal::factory()->for($lead)->create();

    $this->actingAs($user)
        ->get(route('proposals.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('proposals/Index')
            ->has('proposals.data', 1)
            ->where('proposals.data.0.id', $proposal->id));
});

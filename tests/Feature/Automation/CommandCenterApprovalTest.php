<?php

use App\Enums\CommandCenterMode;
use App\Enums\CommandCenterPendingActionStatus;
use App\Models\CommandCenterPendingAction;
use App\Models\Lead;
use App\Models\User;
use App\Services\CommandCenter\CommandCenterApprovalGate;
use App\Support\CommandCenter\CommandCenterContext;

test('assist mode queues mutating actions for approval', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $context = new CommandCenterContext($team, $user, CommandCenterMode::Assist);
    $gate = app(CommandCenterApprovalGate::class);

    expect($gate->needsApproval($context, 'update_lead_status'))->toBeTrue();
    expect($gate->needsApproval($context, 'delete_lead'))->toBeTrue();
});

test('autopilot runs mutating actions without approval except deletes', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $context = new CommandCenterContext($team, $user, CommandCenterMode::Autopilot);
    $gate = app(CommandCenterApprovalGate::class);

    expect($gate->needsApproval($context, 'update_lead_status'))->toBeFalse();
    expect($gate->needsApproval($context, 'delete_lead'))->toBeTrue();
});

test('user can approve a pending delete', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $lead = Lead::factory()->create(['team_id' => $team->id, 'name' => 'To Remove']);

    $pending = CommandCenterPendingAction::query()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'action' => 'delete_lead',
        'summary' => 'Delete test lead',
        'payload' => ['lead_id' => $lead->id],
        'status' => CommandCenterPendingActionStatus::Pending,
    ]);

    $this->actingAs($user)
        ->post(route('command-center.pending.approve', [
            'current_team' => $team->slug,
            'pendingAction' => $pending->id,
        ]))
        ->assertRedirect();

    expect(Lead::query()->whereKey($lead->id)->exists())->toBeFalse();
    expect($pending->fresh()->status)->toBe(CommandCenterPendingActionStatus::Approved);
});

<?php

use App\Enums\CommandCenterMessageSource;
use App\Enums\CommandCenterPendingActionStatus;
use App\Models\CommandCenterMessage;
use App\Models\CommandCenterPendingAction;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\User;
use App\Services\CommandCenter\CommandCenterChatLog;
use App\Services\CommandCenter\CommandCenterNotifier;
use App\Services\CommandCenter\CommandCenterWhatsAppResponder;

test('approving an action posts a completion update in the chat thread', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $lead = Lead::factory()->create(['team_id' => $team->id, 'name' => 'Old Lead']);

    $pending = CommandCenterPendingAction::query()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'action' => 'delete_lead',
        'summary' => 'Delete Old Lead',
        'payload' => ['lead_id' => $lead->id],
        'status' => CommandCenterPendingActionStatus::Pending,
    ]);

    $this->actingAs($user)->post(route('command-center.pending.approve', [
        'current_team' => $team->slug,
        'pendingAction' => $pending->id,
    ]));

    $message = CommandCenterMessage::query()->where('role', 'assistant')->latest('id')->first();

    expect($message->body)->toContain('Deleted lead Old Lead.');
});

test('a finished lead search updates the web thread', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $search = LeadSearch::factory()->completed()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'keyword' => 'window repair',
        'location' => 'Lagos, Nigeria',
        'notify_command_center' => true,
    ]);

    Lead::factory()->count(3)->create(['team_id' => $team->id, 'lead_search_id' => $search->id]);

    app(CommandCenterNotifier::class)->leadSearchFinished($search);

    $message = CommandCenterMessage::query()->where('role', 'assistant')->latest('id')->first();

    expect($message->body)->toContain('window repair')->toContain('3 new lead(s)');
    expect($message->source)->toBe(CommandCenterMessageSource::Web);
});

test('a failed lead search tells the user to try again without provider wording', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $search = LeadSearch::factory()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'notify_command_center' => true,
        'keyword' => 'window repair',
        'location' => 'Austin',
    ]);

    $search->markAsFailed('System error: this run failed on our infrastructure.');

    $message = CommandCenterMessage::query()->where('role', 'assistant')->latest('id')->first();

    expect($message->body)
        ->toContain('window repair')
        ->toContain('could not be completed')
        ->toContain('Please try again')
        ->not->toContain('infrastructure');
});

test('searches not started from the command center do not post updates', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $search = LeadSearch::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);

    $search->markAsFailed('nope');

    expect(CommandCenterMessage::query()->count())->toBe(0);
});

test('updates are also sent over whatsapp when the user last chatted there', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(CommandCenterChatLog::class)->appendUser($team, $user, 'find window fixers', CommandCenterMessageSource::WhatsApp);

    $responder = Mockery::mock(CommandCenterWhatsAppResponder::class);
    $responder->shouldReceive('sendReply')->once()->withArgs(
        fn ($membership, string $text) => str_contains($text, 'Automation finished for Acme'),
    );
    app()->instance(CommandCenterWhatsAppResponder::class, $responder);

    app(CommandCenterNotifier::class)->workflowFinished($team, $user, 'Acme', ['Queued website audit']);

    $message = CommandCenterMessage::query()->where('role', 'assistant')->latest('id')->first();

    expect($message->source)->toBe(CommandCenterMessageSource::WhatsApp);
});

test('updates are not sent over whatsapp when the user last chatted on the web', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(CommandCenterChatLog::class)->appendUser($team, $user, 'hello', CommandCenterMessageSource::Web);

    $responder = Mockery::mock(CommandCenterWhatsAppResponder::class);
    $responder->shouldNotReceive('sendReply');
    app()->instance(CommandCenterWhatsAppResponder::class, $responder);

    app(CommandCenterNotifier::class)->workflowFinished($team, $user, 'Acme', []);

    expect(CommandCenterMessage::query()->where('role', 'assistant')->count())->toBe(1);
});

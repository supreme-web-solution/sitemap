<?php

use App\Enums\CommandCenterMessageSource;
use App\Jobs\ProcessCommandCenterMessage;
use App\Models\CommandCenterMessage;
use App\Models\User;
use App\Services\CommandCenter\CommandCenterChatLog;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('command center message is queued on webhooks worker', function () {
    Queue::fake([ProcessCommandCenterMessage::class]);

    config(['services.openai.key' => 'test-openai-key']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('command-center.messages.store', ['current_team' => $user->currentTeam->slug]), [
            'message' => 'Show my pipeline',
        ])
        ->assertRedirect();

    Queue::assertPushed(ProcessCommandCenterMessage::class, function (ProcessCommandCenterMessage $job) use ($user) {
        return $job->teamId === $user->currentTeam->id
            && $job->userId === $user->id
            && $job->message === 'Show my pipeline'
            && $job->channel === 'web';
    });
});

test('command center loads the latest message page and older history separately', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    foreach (range(1, CommandCenterChatLog::PAGE_SIZE + 1) as $number) {
        CommandCenterMessage::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => 'user',
            'source' => CommandCenterMessageSource::Web,
            'body' => "note {$number}",
        ]);
    }

    $this->actingAs($user)
        ->get(route('command-center.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('messages', CommandCenterChatLog::PAGE_SIZE)
            ->where('messages.0.body', 'note 2')
            ->where('messages.'.(CommandCenterChatLog::PAGE_SIZE - 1).'.body', 'note '.(CommandCenterChatLog::PAGE_SIZE + 1))
            ->where('hasOlderMessages', true));

    $oldestOnFirstPage = CommandCenterMessage::query()->where('body', 'note 2')->firstOrFail();

    $this->getJson(route('command-center.messages.history', [
        'current_team' => $team->slug,
        'before' => $oldestOnFirstPage->id,
    ]))
        ->assertOk()
        ->assertJsonPath('hasOlder', false)
        ->assertJsonPath('messages.0.body', 'note 1')
        ->assertJsonCount(1, 'messages');
});

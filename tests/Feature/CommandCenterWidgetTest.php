<?php

use App\Enums\CommandCenterMessageSource;
use App\Models\User;
use App\Services\CommandCenter\CommandCenterChatLog;
use Illuminate\Support\Facades\Queue;

test('the floating widget can load the latest command center thread as json', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    app(CommandCenterChatLog::class)->appendUser(
        $team,
        $user,
        'Hello from the widget',
        CommandCenterMessageSource::Web,
    );

    $this->actingAs($user)
        ->getJson(route('command-center.messages.history', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertJsonPath('chatProcessing', false)
        ->assertJsonStructure([
            'messages',
            'hasOlder',
            'chatProcessing',
            'aiAvailable',
            'employee' => ['name', 'title', 'avatarUrl'],
        ])
        ->assertJsonFragment(['body' => 'Hello from the widget']);
});

test('posting a widget message returns json instead of an inertia redirect', function () {
    Queue::fake();
    config(['services.openai.key' => 'test-key']);

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)
        ->postJson(route('command-center.messages.store', ['current_team' => $team->slug]), [
            'message' => 'Find plumbers in Austin',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('chatProcessing', true);
});

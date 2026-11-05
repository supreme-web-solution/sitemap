<?php

use App\Ai\Agents\CommandCenterAgent;
use App\Enums\CommandCenterMode;
use App\Models\User;
use App\Services\Ai\AiAvailability;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Support\Facades\Http;

test('command center reads OPENAI_MODEL via command center config', function () {
    config(['command_center.openai_model' => 'gpt-4o-mini']);

    expect(AiAvailability::openaiModel())->toBe('gpt-4o-mini');
});

test('command center agent calls openai even when the app default provider is anthropic', function () {
    $capturedUrl = null;
    $capturedModel = null;

    Http::fake(function ($request) use (&$capturedUrl, &$capturedModel) {
        $capturedUrl = $request->url();
        $capturedModel = json_decode($request->body(), true)['model'] ?? null;

        return Http::response([
            'id' => 'resp_test',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Hello'],
                    ],
                ],
            ],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
        ], 200);
    });

    config([
        'ai.default' => 'anthropic',
        'services.openai.key' => 'sk-test',
        'ai.providers.openai.key' => 'sk-test',
        'command_center.openai_model' => 'gpt-4o-mini',
    ]);

    $user = User::factory()->create();
    $context = new CommandCenterContext($user->currentTeam, $user, CommandCenterMode::Assist);

    (new CommandCenterAgent($context))->prompt('Hi');

    expect($capturedUrl)->toContain('api.openai.com')
        ->and($capturedUrl)->not->toContain('anthropic.com')
        ->and($capturedModel)->toBe('gpt-4o-mini');
});

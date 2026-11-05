<?php

namespace App\Services\CommandCenter;

use App\Ai\Agents\CommandCenterAgent;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommandCenterAgentRunner
{
    public function __construct(protected CommandCenterSession $session) {}

    /**
     * Run the OpenAI employee and return assistant text (empty when tools-only turn).
     */
    public function run(Team $team, User $user, string $message): ?string
    {
        $context = $this->session->context($team, $user);
        $conversationId = $this->session->conversationId($team, $user);

        $agent = new CommandCenterAgent($context);

        if ($conversationId !== null) {
            $response = $agent->continue($conversationId, as: $user)->prompt($message);
        } else {
            $response = $agent->forUser($user)->prompt($message);
        }

        $this->session->persistConversationId($team, $user, $agent->currentConversation());

        $text = trim((string) ($response->text ?? ''));

        return $text !== '' ? $text : null;
    }

    public function finishProcessing(Membership $membership): void
    {
        $membership->forceFill(['command_center_processing_at' => null])->save();

        Cache::forget("command-center-wa-typing:{$membership->id}");
    }

    public function markProcessing(Membership $membership): void
    {
        $membership->forceFill(['command_center_processing_at' => now()])->save();
    }

    public function failProcessing(Membership $membership, Throwable $exception): void
    {
        Log::warning('Command Center agent job failed.', ['message' => $exception->getMessage()]);
        $this->finishProcessing($membership);
    }
}

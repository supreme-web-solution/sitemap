<?php

namespace App\Jobs;

use App\Enums\CommandCenterMessageSource;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use App\Services\CommandCenter\CommandCenterAgentRunner;
use App\Services\CommandCenter\CommandCenterChatLog;
use App\Services\CommandCenter\CommandCenterWhatsAppResponder;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessCommandCenterMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public int $teamId,
        public int $userId,
        public string $message,
        public string $channel = 'web',
    ) {
        $this->onQueue(Queues::WEBHOOKS);
    }

    public function handle(
        CommandCenterAgentRunner $runner,
        CommandCenterWhatsAppResponder $whatsapp,
        CommandCenterChatLog $chatLog,
    ): void {
        $team = Team::query()->find($this->teamId);
        $user = User::query()->find($this->userId);
        $membership = Membership::query()
            ->where('team_id', $this->teamId)
            ->where('user_id', $this->userId)
            ->first();

        if ($team === null || $user === null || $membership === null) {
            return;
        }

        $source = $this->channel === 'whatsapp'
            ? CommandCenterMessageSource::WhatsApp
            : CommandCenterMessageSource::Web;

        if ($this->channel === 'whatsapp') {
            $this->startWhatsAppTypingPulse($membership->id);
        }

        try {
            $reply = $runner->run($team, $user, $this->message);

            if ($reply !== null) {
                $chatLog->appendAssistant($team, $user, $reply, $source);

                if ($this->channel === 'whatsapp') {
                    $whatsapp->sendReply($membership, $reply);
                }
            }
        } catch (Throwable $exception) {
            $runner->failProcessing($membership, $exception);

            return;
        }

        $runner->finishProcessing($membership);
    }

    public function failed(?Throwable $exception): void
    {
        Membership::query()
            ->where('team_id', $this->teamId)
            ->where('user_id', $this->userId)
            ->update(['command_center_processing_at' => null]);
    }

    protected function startWhatsAppTypingPulse(int $membershipId): void
    {
        if (! Cache::add("command-center-wa-typing:{$membershipId}", true, now()->addMinutes(5))) {
            return;
        }

        PulseCommandCenterWhatsAppTyping::dispatch($membershipId);
    }
}

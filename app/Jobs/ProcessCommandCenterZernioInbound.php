<?php

namespace App\Jobs;

use App\Enums\CommandCenterMessageSource;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use App\Services\CommandCenter\CommandCenterChatLog;
use App\Support\PhoneNumber;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessCommandCenterZernioInbound implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
        $this->onQueue(Queues::WEBHOOKS);
    }

    public function handle(CommandCenterChatLog $chatLog): void
    {
        $data = (array) ($this->payload['data'] ?? $this->payload['message'] ?? $this->payload);
        $text = trim((string) ($data['text'] ?? $data['message'] ?? $data['body'] ?? ''));
        $phoneRaw = (string) ($data['from'] ?? $data['participantId'] ?? $data['sender']['id'] ?? $data['sender'] ?? '');
        $conversationId = (string) ($data['conversationId'] ?? $data['conversation_id'] ?? '');
        $e164 = PhoneNumber::e164($phoneRaw);

        if ($text === '' || $e164 === null) {
            return;
        }

        if (preg_match('/\b(LINK-[A-Z0-9]{4,12})\b/i', $text, $matches)) {
            $this->linkMembership(strtoupper($matches[1]), $e164, $conversationId);

            return;
        }

        $membership = Membership::query()
            ->where('command_center_whatsapp_phone', $e164)
            ->whereNotNull('command_center_whatsapp_linked_at')
            ->first();

        if ($membership === null) {
            return;
        }

        if ($conversationId !== '') {
            $membership->forceFill(['command_center_whatsapp_conversation_id' => $conversationId])->save();
        }

        if ($membership->command_center_processing_at !== null) {
            return;
        }

        $team = Team::query()->find($membership->team_id);
        $user = User::query()->find($membership->user_id);

        if ($team !== null && $user !== null) {
            $chatLog->appendUser($team, $user, $text, CommandCenterMessageSource::WhatsApp);
        }

        $membership->forceFill(['command_center_processing_at' => now()])->save();

        ProcessCommandCenterMessage::dispatch(
            $membership->team_id,
            $membership->user_id,
            $text,
            'whatsapp',
        );
    }

    protected function linkMembership(string $code, string $e164, string $conversationId): void
    {
        $membership = Membership::query()
            ->where('command_center_whatsapp_link_code', $code)
            ->first();

        if ($membership === null) {
            return;
        }

        $membership->forceFill([
            'command_center_whatsapp_phone' => $e164,
            'command_center_whatsapp_linked_at' => now(),
            'command_center_whatsapp_conversation_id' => $conversationId !== '' ? $conversationId : $membership->command_center_whatsapp_conversation_id,
        ])->save();
    }
}

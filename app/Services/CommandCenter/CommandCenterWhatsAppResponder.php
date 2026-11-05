<?php

namespace App\Services\CommandCenter;

use App\Models\Membership;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\ZernioClient;
use Illuminate\Support\Facades\Log;

class CommandCenterWhatsAppResponder
{
    public function sendTypingIndicator(Membership $membership): void
    {
        $accountId = config('command_center.whatsapp.account_id');
        $conversationId = $membership->command_center_whatsapp_conversation_id;

        if (! filled($accountId) || ! filled($conversationId) || ! ZernioClient::usesPlatformKey()) {
            return;
        }

        try {
            ZernioClient::platform()->sendWhatsAppTyping((string) $accountId, (string) $conversationId);
        } catch (MessagingException $exception) {
            Log::debug('Command Center WhatsApp typing indicator skipped.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function sendReply(Membership $membership, string $text): void
    {
        $accountId = config('command_center.whatsapp.account_id');
        $conversationId = $membership->command_center_whatsapp_conversation_id;

        if (! filled($accountId) || ! filled($conversationId) || ! ZernioClient::usesPlatformKey()) {
            return;
        }

        try {
            ZernioClient::platform()->sendWhatsAppText((string) $accountId, (string) $conversationId, $text);
        } catch (MessagingException $exception) {
            Log::warning('Command Center WhatsApp reply failed.', ['message' => $exception->getMessage()]);
        }
    }
}

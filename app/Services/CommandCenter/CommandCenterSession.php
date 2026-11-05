<?php

namespace App\Services\CommandCenter;

use App\Enums\CommandCenterMode;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use App\Services\Messaging\ZernioClient;
use App\Support\CommandCenter\CommandCenterContext;
use App\Support\PhoneNumber;
use Illuminate\Support\Str;

class CommandCenterSession
{
    public function __construct(protected CommandCenterChatLog $chatLog) {}

    public function membership(Team $team, User $user): Membership
    {
        return Membership::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function mode(Team $team, User $user): CommandCenterMode
    {
        $raw = $this->membership($team, $user)->command_center_mode;

        return CommandCenterMode::tryFrom((string) $raw) ?? CommandCenterMode::Assist;
    }

    public function updateMode(Team $team, User $user, CommandCenterMode $mode): void
    {
        $this->membership($team, $user)->forceFill([
            'command_center_mode' => $mode->value,
        ])->save();
    }

    public function context(Team $team, User $user): CommandCenterContext
    {
        return new CommandCenterContext($team, $user, $this->mode($team, $user));
    }

    public function conversationId(Team $team, User $user): ?string
    {
        $id = $this->membership($team, $user)->command_center_conversation_id;

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function persistConversationId(Team $team, User $user, ?string $conversationId): void
    {
        if ($conversationId === null) {
            return;
        }

        $membership = $this->membership($team, $user);

        if ($membership->command_center_conversation_id === $conversationId) {
            return;
        }

        $membership->forceFill(['command_center_conversation_id' => $conversationId])->save();
    }

    public function clearConversation(Team $team, User $user): void
    {
        $this->membership($team, $user)->forceFill([
            'command_center_conversation_id' => null,
            'command_center_processing_at' => null,
        ])->save();

        $this->chatLog->clear($team, $user);
    }

    public function isProcessing(Team $team, User $user): bool
    {
        return $this->membership($team, $user)->command_center_processing_at !== null;
    }

    public function ensureWhatsAppLinkCode(Team $team, User $user): string
    {
        $membership = $this->membership($team, $user);

        if (filled($membership->command_center_whatsapp_link_code)) {
            return (string) $membership->command_center_whatsapp_link_code;
        }

        do {
            $code = 'LINK-'.strtoupper(Str::random(5));
        } while (Membership::query()->where('command_center_whatsapp_link_code', $code)->exists());

        $membership->forceFill(['command_center_whatsapp_link_code' => $code])->save();

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    public function whatsAppSetup(Team $team, User $user): array
    {
        $membership = $this->membership($team, $user);
        $number = config('command_center.whatsapp.number');
        $e164 = is_string($number) ? PhoneNumber::e164($number) : null;
        $configured = $e164 !== null
            && filled(config('command_center.whatsapp.webhook_secret'))
            && ZernioClient::usesPlatformKey();

        $linkCode = $configured ? $this->ensureWhatsAppLinkCode($team, $user) : null;
        $digits = $e164 !== null ? ltrim($e164, '+') : null;
        $prefill = $linkCode !== null ? rawurlencode($linkCode) : '';
        $waMeUrl = $digits !== null && $linkCode !== null
            ? "https://wa.me/{$digits}?text={$prefill}"
            : null;

        return [
            'configured' => $configured,
            'platformNumber' => $e164,
            'platformNumberDisplay' => $e164 ?? $number,
            'linkCode' => $linkCode,
            'waMeUrl' => $waMeUrl,
            'linked' => $membership->command_center_whatsapp_linked_at !== null,
            'linkedAt' => $membership->command_center_whatsapp_linked_at?->toISOString(),
            'webhookUrl' => $configured ? route('webhooks.zernio.command-center') : null,
        ];
    }

    /**
     * @return array{messages: list<array{id: string, role: string, body: string, source: string, at: string|null}>, hasOlder: bool}
     */
    public function messagePage(Team $team, User $user, ?int $beforeId = null): array
    {
        return $this->chatLog->pageForUi($team, $user, $beforeId);
    }
}

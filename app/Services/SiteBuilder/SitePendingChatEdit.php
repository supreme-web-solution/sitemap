<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;
use App\Models\SiteChatMessage;

/**
 * Clears a stuck in-flight AI chat edit so the editor unlocks.
 */
final class SitePendingChatEdit
{
    public static function cancelForSite(Site $site): bool
    {
        $pending = $site->chatMessages()
            ->where('role', SiteChatMessage::ROLE_ASSISTANT)
            ->where('status', SiteChatMessage::STATUS_PENDING)
            ->latest('id')
            ->first();

        if ($pending === null) {
            return false;
        }

        return self::cancelMessage($pending);
    }

    public static function cancelMessage(SiteChatMessage $message): bool
    {
        if ($message->status !== SiteChatMessage::STATUS_PENDING) {
            return false;
        }

        $message->forceFill([
            'status' => SiteChatMessage::STATUS_FAILED,
            'body' => __('Edit cancelled.'),
        ])->save();

        SiteEditProgress::forget($message->id);

        return true;
    }
}

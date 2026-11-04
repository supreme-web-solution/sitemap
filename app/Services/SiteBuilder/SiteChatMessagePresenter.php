<?php

namespace App\Services\SiteBuilder;

use App\Models\SiteChatMessage;

class SiteChatMessagePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forEditor(SiteChatMessage $message, bool $siteBusy): array
    {
        $payload = [
            'id' => $message->id,
            'role' => $message->role,
            'body' => $message->body,
            'status' => $message->status,
            'at' => $message->created_at->toISOString(),
            'attachmentUrl' => SiteChatAttachment::url($message->attachment_path),
            'attachmentUrls' => SiteChatAttachment::urls($message->attachmentPaths()),
        ];

        if ($message->role !== SiteChatMessage::ROLE_ASSISTANT || $message->status !== SiteChatMessage::STATUS_FAILED) {
            return $payload;
        }

        $userMessageId = $message->resolvedUserMessageId();
        $attemptsUsed = $userMessageId !== null
            ? SiteChatMessage::attemptCountForUserMessage($message->site_id, $userMessageId)
            : 0;
        $isLatest = $userMessageId !== null && $message->isLatestAssistantForUserMessage($userMessageId);
        $limit = SiteChatMessage::MAX_EDIT_ATTEMPTS;

        $showRetryButton = $userMessageId !== null && $isLatest && $attemptsUsed < $limit;

        $payload['retryAttemptsUsed'] = $attemptsUsed;
        $payload['retryLimit'] = $limit;
        $payload['showRetryButton'] = $showRetryButton;
        $payload['canRetry'] = $showRetryButton && ! $siteBusy;

        if ($isLatest && $attemptsUsed >= $limit) {
            $payload['retryBlockedMessage'] = __(
                'We noticed an issue on our end and could not finish this edit after :count tries. Please wait a few minutes, then send a new message. If it keeps failing, contact support.',
                ['count' => $limit],
            );
        }

        return $payload;
    }
}

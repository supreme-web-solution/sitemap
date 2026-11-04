<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\Message;

/**
 * Read prior Laravel AI turns for a site editor session (RemembersConversations storage).
 */
class SiteEditConversation
{
    public function __construct(protected ConversationStore $store) {}

    /**
     * Plain-text transcript for model prompts, or empty when there is no session yet.
     */
    public function transcript(Site $site, int $limit = 12): string
    {
        $conversationId = $site->ai_conversation_id;

        if (! is_string($conversationId) || $conversationId === '') {
            return '';
        }

        /** @var Collection<int, Message> $messages */
        $messages = $this->store->getLatestConversationMessages($conversationId, $limit);

        if ($messages->isEmpty()) {
            return '';
        }

        $lines = $messages->map(function (Message $message): string {
            $role = ucfirst(is_string($message->role) ? $message->role : $message->role->value);

            return "{$role}: ".trim($message->content);
        });

        return "Prior edits on this site (newest last):\n".$lines->implode("\n");
    }

    public function persistConversationId(Site $site, ?string $conversationId): void
    {
        if ($conversationId === null || $conversationId === $site->ai_conversation_id) {
            return;
        }

        $site->forceFill(['ai_conversation_id' => $conversationId])->save();
    }
}

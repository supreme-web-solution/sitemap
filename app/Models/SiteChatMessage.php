<?php

namespace App\Models;

use Database\Factories\SiteChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of the conversation used to edit a website with AI.
 *
 * @property int $id
 * @property int $site_id
 * @property int|null $user_id
 * @property string $role
 * @property string $body
 * @property string|null $attachment_path
 * @property list<string>|null $attachment_paths
 * @property string $status
 * @property int|null $site_version_id
 * @property int|null $user_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site $site
 * @property-read SiteChatMessage|null $userMessage
 */
#[Fillable(['site_id', 'user_id', 'role', 'body', 'attachment_path', 'attachment_paths', 'status', 'site_version_id', 'user_message_id'])]
class SiteChatMessage extends Model
{
    /** @use HasFactory<SiteChatMessageFactory> */
    use HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    /**
     * Minutes after which a pending edit is treated as lost, so the chat never stays locked.
     */
    public const STALE_AFTER_MINUTES = 20;

    /**
     * Maximum assistant runs (initial + retries) for one user message.
     */
    public const MAX_EDIT_ATTEMPTS = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachment_paths' => 'array',
        ];
    }

    /**
     * Stored chat attachment paths (new JSON column, with legacy single path fallback).
     *
     * @return list<string>
     */
    public function attachmentPaths(): array
    {
        $paths = $this->attachment_paths;

        if (is_array($paths) && $paths !== []) {
            return array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
        }

        if (filled($this->attachment_path)) {
            return [(string) $this->attachment_path];
        }

        return [];
    }

    public function hasAttachments(): bool
    {
        return $this->attachmentPaths() !== [];
    }

    public static function attemptCountForUserMessage(int $siteId, int $userMessageId): int
    {
        return self::assistantRepliesQuery($siteId, $userMessageId)->count();
    }

    /**
     * Assistant messages tied to one user instruction (until the next user message).
     *
     * @return Builder<self>
     */
    public static function assistantRepliesQuery(int $siteId, int $userMessageId): Builder
    {
        $nextUserMessageId = self::query()
            ->where('site_id', $siteId)
            ->where('role', self::ROLE_USER)
            ->where('id', '>', $userMessageId)
            ->orderBy('id')
            ->value('id');

        $query = self::query()
            ->where('site_id', $siteId)
            ->where('role', self::ROLE_ASSISTANT)
            ->where('id', '>', $userMessageId);

        if ($nextUserMessageId !== null) {
            $query->where('id', '<', $nextUserMessageId);
        }

        return $query;
    }

    public function resolvedUserMessageId(): ?int
    {
        if ($this->user_message_id !== null) {
            return $this->user_message_id;
        }

        if ($this->role !== self::ROLE_ASSISTANT) {
            return null;
        }

        $previous = self::query()
            ->where('site_id', $this->site_id)
            ->where('role', self::ROLE_USER)
            ->where('id', '<', $this->id)
            ->latest('id')
            ->value('id');

        return is_int($previous) ? $previous : null;
    }

    public function isLatestAssistantForUserMessage(int $userMessageId): bool
    {
        $latestId = self::assistantRepliesQuery($this->site_id, $userMessageId)
            ->latest('id')
            ->value('id');

        return $latestId === $this->id;
    }

    /**
     * @return BelongsTo<SiteChatMessage, $this>
     */
    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'user_message_id');
    }

    /**
     * Get the site the message is about.
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the revision saved just before this edit was applied.
     *
     * @return BelongsTo<SiteVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SiteVersion::class, 'site_version_id');
    }
}

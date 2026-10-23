<?php

namespace App\Models;

use Database\Factories\SiteChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string $status
 * @property int|null $site_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site $site
 */
#[Fillable(['site_id', 'user_id', 'role', 'body', 'status', 'site_version_id'])]
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
    public const STALE_AFTER_MINUTES = 5;

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

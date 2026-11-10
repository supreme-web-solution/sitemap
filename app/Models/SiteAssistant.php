<?php

namespace App\Models;

use Database\Factories\SiteAssistantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The AI chat assistant embedded in a generated website.
 *
 * @property int $id
 * @property int $team_id
 * @property int $site_id
 * @property string $public_key
 * @property bool $enabled
 * @property string $name
 * @property string $avatar
 * @property string $voice
 * @property string|null $greeting
 * @property string|null $knowledge
 * @property Carbon|null $knowledge_generated_at
 * @property int $messages_count
 * @property int $leads_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Site $site
 */
#[Fillable([
    'team_id',
    'site_id',
    'public_key',
    'enabled',
    'name',
    'avatar',
    'voice',
    'greeting',
    'knowledge',
    'knowledge_generated_at',
])]
class SiteAssistant extends Model
{
    /** @use HasFactory<SiteAssistantFactory> */
    use HasFactory;

    /**
     * The longest knowledge base the assistant reads, in characters.
     */
    public const MAX_KNOWLEDGE_LENGTH = 12000;

    /**
     * Get the team that owns the assistant.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the website the assistant answers questions about.
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'knowledge_generated_at' => 'datetime',
        ];
    }
}

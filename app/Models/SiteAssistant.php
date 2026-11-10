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
 * @property string|null $presenter_id
 * @property string|null $presenter_name
 * @property string|null $presenter_gender
 * @property string|null $presenter_thumbnail_url
 * @property string $voice_id
 * @property string|null $voice_name
 * @property bool $talking_avatar_enabled
 * @property string|null $simli_face_id
 * @property string|null $simli_face_name
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
    'presenter_id',
    'presenter_name',
    'presenter_gender',
    'presenter_thumbnail_url',
    'voice_id',
    'voice_name',
    'talking_avatar_enabled',
    'simli_face_id',
    'simli_face_name',
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
     * Whether a photoreal presenter has been chosen.
     */
    public function hasPresenter(): bool
    {
        return filled($this->presenter_id) && filled($this->presenter_thumbnail_url);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'talking_avatar_enabled' => 'boolean',
            'knowledge_generated_at' => 'datetime',
        ];
    }
}

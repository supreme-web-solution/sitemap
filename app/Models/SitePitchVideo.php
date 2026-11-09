<?php

namespace App\Models;

use App\Enums\PitchVideoStatus;
use Database\Factories\SitePitchVideoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The AI presenter video that introduces a generated website to the business owner.
 *
 * @property int $id
 * @property int $team_id
 * @property int $site_id
 * @property string $public_token
 * @property PitchVideoStatus $status
 * @property string|null $script
 * @property string|null $presenter_id
 * @property string|null $presenter_name
 * @property string|null $presenter_gender
 * @property string|null $presenter_thumbnail_url
 * @property string|null $voice_id
 * @property string|null $voice_name
 * @property string|null $voice_provider
 * @property string|null $did_clip_id
 * @property string|null $video_path
 * @property string|null $error
 * @property Carbon|null $generation_started_at
 * @property Carbon|null $generated_at
 * @property Carbon|null $viewed_at
 * @property int $views_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Site $site
 */
#[Fillable([
    'team_id',
    'site_id',
    'status',
    'script',
    'presenter_id',
    'presenter_name',
    'presenter_gender',
    'presenter_thumbnail_url',
    'voice_id',
    'voice_name',
    'voice_provider',
    'did_clip_id',
    'video_path',
    'error',
    'generation_started_at',
    'generated_at',
])]
class SitePitchVideo extends Model
{
    /** @use HasFactory<SitePitchVideoFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (SitePitchVideo $video) {
            $video->public_token ??= Str::random(40);
        });
    }

    /**
     * Get the team that owns the video.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the website the video introduces.
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Whether the video file is stored and can be watched.
     */
    public function isReady(): bool
    {
        return $this->status === PitchVideoStatus::Ready && filled($this->video_path);
    }

    /**
     * Whether a video file exists to watch, even while a newer one is being made or a retry failed.
     */
    public function isWatchable(): bool
    {
        return filled($this->video_path);
    }

    /**
     * Whether D-ID is still rendering the video.
     */
    public function isGenerating(): bool
    {
        return $this->status === PitchVideoStatus::Generating;
    }

    /**
     * The page the business owner opens to watch the video.
     */
    public function watchUrl(): string
    {
        return route('pitch-videos.public', $this->public_token);
    }

    /**
     * The direct URL of the stored video file.
     */
    public function videoUrl(): ?string
    {
        return filled($this->video_path) ? Storage::disk('public')->url($this->video_path) : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PitchVideoStatus::class,
            'generation_started_at' => 'datetime',
            'generated_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }
}

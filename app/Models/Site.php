<?php

namespace App\Models;

use App\Enums\SiteTemplate;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @phpstan-type SiteContent array{
 *     accent: string,
 *     headline: string,
 *     tagline: string,
 *     cta_label: string,
 *     about: string,
 *     sections?: list<string>,
 *     reviews_style?: string,
 *     pages?: list<string>,
 *     services: list<array{title: string, description: string, slug?: string, body?: string}>,
 *     faqs: list<array{question: string, answer: string}>,
 *     copy?: array<string, string>,
 *     design?: array<string, string>,
 *     section_styles?: array<string, string>,
 * }
 *
 * @property int $id
 * @property int $team_id
 * @property int $lead_id
 * @property string $slug
 * @property SiteTemplate $template
 * @property SiteContent $content
 * @property int $revision
 * @property int $views_count
 * @property Carbon|null $last_viewed_at
 * @property array<string, int>|null $preview_section_views
 * @property list<array{key: string, label: string, status: string, detail: string}>|null $delivery_findings
 * @property Carbon|null $published_at
 * @property Carbon|null $staging_published_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Lead $lead
 */
#[Fillable(['lead_id', 'slug', 'template', 'design_template_id', 'content'])]
#[Hidden(['preview_password'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * The number of earlier versions kept for each site.
     */
    public const MAX_VERSIONS = 30;

    /**
     * Minutes of continuous visual editing that share one history entry.
     */
    public const EDIT_SESSION_MINUTES = 15;

    /**
     * Bootstrap the model and its traits.
     *
     * Any change to the template or content moves the revision on, so a stale editor
     * (another tab, a teammate or an AI edit) is detected instead of overwriting newer work.
     */
    protected static function booted(): void
    {
        static::saving(function (Site $site) {
            if ($site->exists && ! $site->isDirty('revision') && $site->isDirty(['template', 'content'])) {
                $site->revision++;
            }
        });
    }

    /**
     * Get the team that owns the site.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the lead the site was generated for.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the AI editing conversation for the site.
     *
     * @return HasMany<SiteChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(SiteChatMessage::class);
    }

    /**
     * Get the saved revisions of the site.
     *
     * @return HasMany<SiteVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SiteVersion::class);
    }

    /**
     * Get the custom domains connected to the site.
     *
     * @return HasMany<SiteDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(SiteDomain::class);
    }

    /**
     * Get the catalog entry chosen before generation, if any.
     *
     * @return BelongsTo<SiteDesignTemplate, $this>
     */
    public function designTemplate(): BelongsTo
    {
        return $this->belongsTo(SiteDesignTemplate::class);
    }

    /**
     * Whether the site has been published to its domains.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Whether the site is served on its staging hostname.
     */
    public function isStagingPublished(): bool
    {
        return $this->staging_published_at !== null;
    }

    /**
     * Get the verified domain visitors are sent to. Other domains redirect to it.
     */
    public function primaryDomain(): ?SiteDomain
    {
        return $this->domains()->verified()->orderBy('verified_at')->orderBy('id')->first();
    }

    /**
     * Get the blog posts written for the site.
     *
     * @return HasMany<SitePost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(SitePost::class);
    }

    /**
     * Get comments left on the public preview.
     *
     * @return HasMany<PreviewComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PreviewComment::class);
    }

    /**
     * Get broken links found by the last crawl.
     *
     * @return HasMany<SiteBrokenLink, $this>
     */
    public function brokenLinks(): HasMany
    {
        return $this->hasMany(SiteBrokenLink::class);
    }

    /**
     * Get stored backups of published versions.
     *
     * @return HasMany<SiteBackup, $this>
     */
    public function backups(): HasMany
    {
        return $this->hasMany(SiteBackup::class);
    }

    /**
     * Save the current template and content as a revision.
     */
    public function snapshot(?int $userId = null, ?string $label = null): SiteVersion
    {
        $version = $this->versions()->create([
            'user_id' => $userId,
            'template' => $this->template,
            'content' => $this->content,
            'label' => $label,
        ]);

        $keep = $this->versions()->latest('id')->limit(self::MAX_VERSIONS)->pluck('id');

        $this->versions()->whereNotIn('id', $keep)->delete();

        return $version;
    }

    /**
     * Save a revision for a stretch of visual editing, at most one per editing session,
     * so autosaves do not push real history out of the version limit.
     */
    public function snapshotForEditSession(?int $userId): ?SiteVersion
    {
        $label = __('Visual editing session');

        $recent = $this->versions()
            ->where('label', $label)
            ->where('created_at', '>=', now()->subMinutes(self::EDIT_SESSION_MINUTES))
            ->exists();

        return $recent ? null : $this->snapshot($userId, $label);
    }

    /**
     * Record a visit to the public preview.
     */
    public function recordView(): void
    {
        $this->newQuery()->whereKey($this->id)->incrementEach(
            ['views_count' => 1],
            ['last_viewed_at' => now()],
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template' => SiteTemplate::class,
            'content' => 'array',
            'last_viewed_at' => 'datetime',
            'published_at' => 'datetime',
            'staging_published_at' => 'datetime',
            'approved_at' => 'datetime',
            'preview_expires_at' => 'datetime',
            'preview_password' => 'hashed',
            'transferred_at' => 'datetime',
            'last_uptime_at' => 'datetime',
            'uptime_ok' => 'boolean',
            'last_crawled_at' => 'datetime',
            'preview_section_views' => 'array',
            'delivery_findings' => 'array',
        ];
    }
}

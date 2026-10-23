<?php

namespace App\Models;

use App\Enums\SiteTemplate;
use Database\Factories\SiteVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property int|null $user_id
 * @property SiteTemplate $template
 * @property array<string, mixed> $content
 * @property string|null $label
 * @property Carbon|null $created_at
 * @property-read Site $site
 * @property-read User|null $user
 */
#[Fillable(['site_id', 'user_id', 'template', 'content', 'label'])]
class SiteVersion extends Model
{
    /** @use HasFactory<SiteVersionFactory> */
    use HasFactory;

    /**
     * Get the site the version belongs to.
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the user who saved the version.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        ];
    }
}

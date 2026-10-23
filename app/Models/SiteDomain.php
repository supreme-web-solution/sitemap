<?php

namespace App\Models;

use Database\Factories\SiteDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property string $hostname
 * @property string $verification_token
 * @property Carbon|null $verified_at
 * @property string|null $verified_hostname
 * @property Carbon|null $last_checked_at
 * @property string|null $check_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site $site
 */
#[Fillable(['hostname', 'verification_token'])]
class SiteDomain extends Model
{
    /** @use HasFactory<SiteDomainFactory> */
    use HasFactory;

    /**
     * The DNS name where the ownership TXT record is expected.
     */
    public const VERIFICATION_PREFIX = '_sitemap-verify.';

    /**
     * Limit the query to domains whose ownership has been proven.
     *
     * @param  Builder<SiteDomain>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->whereNotNull('verified_at');
    }

    /**
     * Whether ownership of the domain has been proven.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * The DNS name of the ownership TXT record.
     */
    public function verificationRecordName(): string
    {
        return self::VERIFICATION_PREFIX.$this->hostname;
    }

    /**
     * Get the site the domain belongs to.
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
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\LostReason;
use App\Enums\PaymentProvider;
use App\Enums\SiteGenerationStatus;
use App\Enums\SiteVibe;
use App\Observers\LeadObserver;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $lead_search_id
 * @property string $place_id
 * @property string $name
 * @property string|null $category
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property float|null $latitude
 * @property float|null $longitude
 * @property float|null $rating
 * @property int $reviews_count
 * @property array<string, string>|null $opening_hours
 * @property string|null $description
 * @property string|null $photo_url
 * @property string|null $maps_url
 * @property LeadStatus $status
 * @property int|null $deal_value
 * @property string|null $notes
 * @property Carbon|null $next_follow_up_at
 * @property Carbon|null $last_contacted_at
 * @property int|null $opportunity_score
 * @property list<array{key: string, label: string, points: int, max: int, reason: string}>|null $opportunity_breakdown
 * @property array<string, mixed>|null $website_audit
 * @property Carbon|null $website_audited_at
 * @property SiteGenerationStatus $website_audit_status
 * @property SiteVibe|null $vibe
 * @property string|null $logo_path
 * @property list<string>|null $photo_paths
 * @property Carbon|null $details_confirmed_at
 * @property SiteGenerationStatus $site_generation_status
 * @property string|null $site_generation_error
 * @property string|null $portal_token
 * @property string $concepts_status
 * @property Carbon|null $concepts_requested_at
 * @property string|null $phone_e164
 * @property Carbon|null $messaging_consent_at
 * @property Carbon|null $messaging_opted_out_at
 * @property string|null $stripe_customer_id
 * @property string|null $stripe_payment_method_id
 * @property string|null $paypal_vault_id
 * @property Carbon|null $reviews_fetched_at
 * @property SiteGenerationStatus $reviews_fetch_status
 * @property string|null $reviews_fetch_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read LeadSearch|null $leadSearch
 * @property-read Site|null $site
 */
#[Fillable([
    'lead_search_id',
    'place_id',
    'name',
    'category',
    'phone',
    'email',
    'website',
    'address',
    'city',
    'state',
    'country',
    'latitude',
    'longitude',
    'rating',
    'reviews_count',
    'opening_hours',
    'description',
    'photo_url',
    'maps_url',
    'status',
    'lost_reason',
    'assigned_user_id',
    'deal_value',
    'notes',
    'next_follow_up_at',
    'last_contacted_at',
    'opportunity_score',
    'opportunity_breakdown',
    'website_audit',
    'website_audited_at',
    'website_audit_status',
    'vibe',
    'logo_path',
    'photo_paths',
    'details_confirmed_at',
    'site_generation_status',
    'site_generation_error',
    'concepts_status',
    'concepts_requested_at',
    'reviews_fetched_at',
    'reviews_fetch_status',
    'reviews_fetch_error',
])]
#[ObservedBy(LeadObserver::class)]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * The most reviews shown on a generated website.
     */
    public const MAX_TESTIMONIALS = 12;

    /**
     * The fewest reviews worth showing as a testimonials section.
     */
    public const MIN_TESTIMONIALS = 3;

    /**
     * Get the team that owns the lead.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the search that found the lead.
     *
     * @return BelongsTo<LeadSearch, $this>
     */
    public function leadSearch(): BelongsTo
    {
        return $this->belongsTo(LeadSearch::class);
    }

    /**
     * Get the website generated for the lead.
     *
     * @return HasOne<Site, $this>
     */
    public function site(): HasOne
    {
        return $this->hasOne(Site::class);
    }

    /**
     * Whether Stripe can charge this lead off-session for recurring billing.
     */
    public function hasStripeCardOnFile(): bool
    {
        return filled($this->stripe_customer_id) && filled($this->stripe_payment_method_id);
    }

    /**
     * Whether PayPal can charge this lead off-session for recurring billing.
     */
    public function hasPayPalVaultOnFile(): bool
    {
        return filled($this->paypal_vault_id);
    }

    /**
     * Whether the given payment provider can auto-charge this lead.
     */
    public function hasCardOnFileFor(PaymentProvider $provider): bool
    {
        return match ($provider) {
            PaymentProvider::Stripe => $this->hasStripeCardOnFile(),
            PaymentProvider::PayPal => $this->hasPayPalVaultOnFile(),
            default => false,
        };
    }

    /**
     * Get the follow-up sequence enrollments for the lead.
     *
     * @return HasMany<SequenceEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class);
    }

    /**
     * Get the tasks on this lead.
     *
     * @return HasMany<LeadTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(LeadTask::class);
    }

    /**
     * Apply the lead list filters.
     *
     * @param  Builder<Lead>  $query
     * @param  array{status?: string, search?: string, tier?: string, follow_up?: string, assigned?: string, ideal?: string}  $filters
     */
    #[Scope]
    protected function filteredBy(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")))
            ->when($filters['tier'] ?? null, fn (Builder $query, string $tier) => match ($tier) {
                'hot' => $query->where('opportunity_score', '>=', config('prospecting.score_tiers.hot')),
                'warm' => $query->whereBetween('opportunity_score', [
                    config('prospecting.score_tiers.warm'),
                    config('prospecting.score_tiers.hot') - 1,
                ]),
                default => $query->where('opportunity_score', '<', config('prospecting.score_tiers.warm')),
            })
            ->when(($filters['follow_up'] ?? null) === 'due', fn (Builder $query) => $query
                ->whereDate('next_follow_up_at', '<=', today())
                ->whereNotIn('status', [LeadStatus::Won, LeadStatus::Lost]))
            ->when($filters['assigned'] ?? null, fn (Builder $query, string $assigned) => $query->where('assigned_user_id', (int) $assigned));
    }

    /**
     * Apply one of the supported sort orders.
     *
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function sortedBy(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'reviews' => $query->orderByDesc('reviews_count'),
            'newest' => $query,
            'follow_up' => $query
                ->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('next_follow_up_at'),
            default => $query->orderByDesc('opportunity_score'),
        };

        $query->orderByDesc('id');
    }

    /**
     * Get the lead's timeline of activity.
     *
     * @return HasMany<LeadActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    /**
     * Get the Google reviews collected for the lead.
     *
     * @return HasMany<LeadReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(LeadReview::class);
    }

    /**
     * Get the reviews that may be shown on the website, best first.
     *
     * @return Collection<int, LeadReview>
     */
    public function testimonials(): Collection
    {
        return $this->reviews()
            ->where('is_visible', true)
            ->orderByDesc('rating')
            ->orderBy('id')
            ->limit(self::MAX_TESTIMONIALS)
            ->get();
    }

    /**
     * The Google Maps URL used to look up and link to the business's reviews.
     */
    public function reviewsUrl(): string
    {
        return $this->maps_url ?: 'https://www.google.com/maps/place/?q=place_id:'.rawurlencode($this->place_id);
    }

    /**
     * Get the alternative designs offered before a website is chosen.
     *
     * @return HasMany<SiteConcept, $this>
     */
    public function concepts(): HasMany
    {
        return $this->hasMany(SiteConcept::class);
    }

    /**
     * Get the SMS and WhatsApp messages exchanged with the lead.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Add an entry to the lead's timeline.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function recordActivity(LeadActivityType $type, string $summary, ?array $data = null, ?int $userId = null): LeadActivity
    {
        return $this->activities()->create([
            'team_id' => $this->team_id,
            'user_id' => $userId,
            'type' => $type,
            'summary' => $summary,
            'data' => $data,
        ]);
    }

    /**
     * Get the public URL of the owner's logo.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Get public URLs of the owner's photos, falling back to the listing photo.
     *
     * @return list<string>
     */
    public function photoUrls(): array
    {
        $uploaded = array_map(
            fn (string $path) => Storage::disk('public')->url($path),
            $this->photo_paths ?? [],
        );

        if ($uploaded !== []) {
            return $uploaded;
        }

        return $this->photo_url ? [$this->photo_url] : [];
    }

    /**
     * The link where the client follows their project, when the portal is switched on.
     */
    public function portalUrl(): ?string
    {
        return $this->portal_token ? route('portal.show', $this->portal_token) : null;
    }

    /**
     * Get the invoices sent to the lead.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the proposals written for the lead.
     *
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'rating' => 'float',
            'opening_hours' => 'array',
            'status' => LeadStatus::class,
            'lost_reason' => LostReason::class,
            'vibe' => SiteVibe::class,
            'photo_paths' => 'array',
            'details_confirmed_at' => 'datetime',
            'next_follow_up_at' => 'date',
            'last_contacted_at' => 'datetime',
            'opportunity_breakdown' => 'array',
            'website_audit' => 'array',
            'website_audit_status' => SiteGenerationStatus::class,
            'website_audited_at' => 'datetime',
            'site_generation_status' => SiteGenerationStatus::class,
            'concepts_status' => SiteGenerationStatus::class,
            'reviews_fetched_at' => 'datetime',
            'reviews_summary' => 'array',
            'competitor_audit' => 'array',
            'competitor_audited_at' => 'datetime',
            'concepts_requested_at' => 'datetime',
            'messaging_consent_at' => 'datetime',
            'messaging_opted_out_at' => 'datetime',
            'reviews_fetch_status' => SiteGenerationStatus::class,
        ];
    }
}

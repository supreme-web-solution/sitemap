<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\PlanFeature;
use App\Enums\TeamRole;
use App\Enums\WebsiteFilter;
use App\Services\Billing\UsageMeter;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property int|null $plan_id
 * @property string|null $brand_name
 * @property string|null $brand_logo_url
 * @property string|null $brand_color
 * @property string|null $brand_email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, TeamInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, LeadSearch> $leadSearches
 * @property-read Collection<int, Lead> $leads
 * @property-read Collection<int, Site> $sites
 * @property array<string, int>|null $score_weights
 * @property array<string, mixed>|null $ideal_customer
 * @property string|null $preview_hostname
 * @property string|null $preview_hostname_token
 * @property Carbon|null $preview_hostname_verified_at
 * @property Carbon|null $preview_hostname_checked_at
 * @property string|null $preview_hostname_check_error
 */
#[Fillable(['name', 'slug', 'is_personal'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * What public pages (portal, proposals, invoices) show as the agency's identity.
     *
     * @return array{name: string, logo: string|null, color: string, email: string|null, poweredBy: bool}
     */
    public function brand(): array
    {
        return [
            'name' => $this->brand_name ?: $this->name,
            'logo' => $this->brand_logo_url,
            'color' => $this->brand_color ?: '#18181b',
            'email' => $this->brand_email,
            'poweredBy' => ! app(UsageMeter::class)->allows($this, PlanFeature::WhiteLabel),
        ];
    }

    /**
     * Limit the query to teams whose branded preview hostname is verified.
     *
     * @param  Builder<Team>  $query
     */
    public function scopeVerifiedPreviewHostname(Builder $query, string $hostname): void
    {
        $query->where('preview_hostname', strtolower($hostname))
            ->whereNotNull('preview_hostname_verified_at');
    }

    /**
     * Whether the team serves previews on its own hostname.
     */
    public function hasVerifiedPreviewHostname(): bool
    {
        return filled($this->preview_hostname) && $this->preview_hostname_verified_at !== null;
    }

    /**
     * Origin (scheme + host) for shareable preview links, when verified.
     */
    public function brandedPreviewOrigin(): ?string
    {
        if (! $this->hasVerifiedPreviewHostname()) {
            return null;
        }

        $scheme = config('sites.force_https') ? 'https' : 'http';

        return $scheme.'://'.strtolower((string) $this->preview_hostname);
    }

    /**
     * Get the team's SMS and WhatsApp connection.
     *
     * @return HasOne<MessagingAccount, $this>
     */
    public function messagingAccount(): HasOne
    {
        return $this->hasOne(MessagingAccount::class);
    }

    /**
     * Get the team's connected email inbox for reply sync.
     *
     * @return HasOne<MailConnection, $this>
     */
    public function mailConnection(): HasOne
    {
        return $this->hasOne(MailConnection::class);
    }

    /**
     * Get the services the team sells.
     *
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /**
     * Get the proposals the team has written.
     *
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    /**
     * Get the plan an administrator assigned to the team.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the plan that applies to the team: the assigned one, or the default plan.
     */
    public function effectivePlan(): ?Plan
    {
        return $this->plan ?? Plan::default();
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get all lead searches run by this team.
     *
     * @return HasMany<LeadSearch, $this>
     */
    public function leadSearches(): HasMany
    {
        return $this->hasMany(LeadSearch::class);
    }

    /**
     * Get all leads belonging to this team.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Get all email templates belonging to this team.
     *
     * @return HasMany<EmailTemplate, $this>
     */
    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    /**
     * Get the activity recorded across all of this team's leads.
     *
     * @return HasMany<LeadActivity, $this>
     */
    public function leadActivities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    /**
     * Get the invoices issued by this team.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the recurring billing schedules set up by this team.
     *
     * @return HasMany<RecurringInvoice, $this>
     */
    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    /**
     * Get the payment providers the team has connected.
     *
     * @return HasMany<PaymentAccount, $this>
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    /**
     * Get all generated sites belonging to this team.
     *
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Get the automated email sequences for this team.
     *
     * @return HasMany<FollowUpSequence, $this>
     */
    public function followUpSequences(): HasMany
    {
        return $this->hasMany(FollowUpSequence::class);
    }

    /**
     * Get the niche research briefs generated for this team.
     *
     * @return HasMany<NicheBrief, $this>
     */
    public function nicheBriefs(): HasMany
    {
        return $this->hasMany(NicheBrief::class);
    }

    /**
     * Get the tasks recorded on this team's leads.
     *
     * @return HasMany<LeadTask, $this>
     */
    public function leadTasks(): HasMany
    {
        return $this->hasMany(LeadTask::class);
    }

    /**
     * Names of people on the team, for assignment dropdowns.
     *
     * @return list<array{id: int, name: string}>
     */
    public function memberOptions(): array
    {
        return $this->members()->orderBy('name')->get()
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    /**
     * Limit a lead query to businesses that match the saved ideal customer.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function applyIdealCustomerFilter(Builder $query): Builder
    {
        $ideal = $this->ideal_customer ?? [];
        $categories = array_values(array_filter($ideal['categories'] ?? []));
        $cities = array_values(array_filter($ideal['cities'] ?? []));

        if ($categories !== []) {
            $query->where(function (Builder $query) use ($categories) {
                foreach ($categories as $category) {
                    $query->orWhere('category', 'like', '%'.$category.'%');
                }
            });
        }

        if ($cities !== []) {
            $query->where(function (Builder $query) use ($cities) {
                foreach ($cities as $city) {
                    $query->orWhere('city', 'like', '%'.$city.'%');
                }
            });
        }

        if (isset($ideal['minimumRating']) && is_numeric($ideal['minimumRating'])) {
            $query->where('rating', '>=', $ideal['minimumRating']);
        }

        $websiteFilter = WebsiteFilter::tryFrom((string) ($ideal['websiteFilter'] ?? ''));

        if ($websiteFilter === WebsiteFilter::WithoutWebsite) {
            $query->whereNull('website');
        }

        if ($websiteFilter === WebsiteFilter::WithWebsite) {
            $query->whereNotNull('website');
        }

        return $query;
    }

    /**
     * Opportunity score weights for this team, falling back to the app defaults.
     *
     * @return array<string, int>
     */
    public function scoreWeights(): array
    {
        $defaults = config('prospecting.score_weights');
        $custom = $this->score_weights;

        if (! is_array($custom)) {
            return $defaults;
        }

        $weights = [];

        foreach ($defaults as $key => $fallback) {
            $weights[$key] = (int) ($custom[$key] ?? $fallback);
        }

        return array_sum($weights) === 100 ? $weights : $defaults;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'ideal_customer' => 'array',
            'score_weights' => 'array',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

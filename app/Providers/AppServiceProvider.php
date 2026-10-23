<?php

namespace App\Providers;

use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\SitePost;
use App\Services\LeadFinder\LeadFinder;
use App\Services\LeadFinder\MindcaseLeadFinder;
use App\Services\ReviewFinder\MindcaseReviewFinder;
use App\Services\ReviewFinder\ReviewFinder;
use App\Services\SiteBuilder\ClaudeSitePlanner;
use App\Services\SiteBuilder\OpenAiSitePlanner;
use App\Services\SiteBuilder\RuleBasedCopywriter;
use App\Services\SiteBuilder\SiteCache;
use App\Services\SiteBuilder\SiteCopywriter;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LeadFinder::class, fn () => new MindcaseLeadFinder(
            apiKey: config('services.mindcase.key'),
            baseUrl: config('services.mindcase.base_url'),
        ));

        $this->app->bind(ReviewFinder::class, fn () => new MindcaseReviewFinder(
            apiKey: config('services.mindcase.key'),
            baseUrl: config('services.mindcase.base_url'),
            maxReviews: config('prospecting.reviews.fetch_limit'),
        ));

        $this->app->bind(SiteCopywriter::class, function ($app) {
            $fallback = $app->make(RuleBasedCopywriter::class);

            return match ($this->sitePlannerProvider()) {
                'anthropic' => new ClaudeSitePlanner(
                    apiKey: config('services.anthropic.key'),
                    model: config('services.anthropic.model'),
                    fallback: $fallback,
                ),
                'openai' => new OpenAiSitePlanner(
                    apiKey: config('services.openai.key'),
                    model: config('services.openai.model'),
                    fallback: $fallback,
                ),
                default => $fallback,
            };
        });
    }

    /**
     * Pick the AI provider for site planning: the preferred one when its key
     * is set, otherwise whichever provider has a key.
     */
    protected function sitePlannerProvider(): ?string
    {
        $configured = array_keys(array_filter([
            'anthropic' => filled(config('services.anthropic.key')),
            'openai' => filled(config('services.openai.key')),
        ]));

        $preferred = config('services.site_planner.provider');

        return in_array($preferred, $configured, true) ? $preferred : ($configured[0] ?? null);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureSiteCache();
    }

    /**
     * Retire the cached pages of a published site whenever anything it shows changes.
     */
    protected function configureSiteCache(): void
    {
        $flushLead = fn (int $leadId) => Site::query()->where('lead_id', $leadId)->pluck('id')->each(fn (int $id) => SiteCache::flush($id));

        Site::saved(fn (Site $site) => SiteCache::flush($site->id));
        Site::deleted(fn (Site $site) => SiteCache::flush($site->id));
        SitePost::saved(fn (SitePost $post) => SiteCache::flush($post->site_id));
        SitePost::deleted(fn (SitePost $post) => SiteCache::flush($post->site_id));
        SiteDomain::saved(fn (SiteDomain $domain) => SiteCache::flush($domain->site_id));
        SiteDomain::deleted(fn (SiteDomain $domain) => SiteCache::flush($domain->site_id));
        Lead::saved(fn (Lead $lead) => $flushLead($lead->id));
        LeadReview::saved(fn (LeadReview $review) => $flushLead($review->lead_id));
    }

    /**
     * Configure rate limits for endpoints that cost money or heavy work.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('lead-searches', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('lead-audits', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('review-fetches', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('messaging-send', fn (object $job) => Limit::perMinute((int) config('prospecting.messages_per_minute', 30))->by($job->message->team_id));

        RateLimiter::for('outreach', fn (Request $request) => Limit::perMinute((int) config('prospecting.outreach_emails_per_minute'))->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('preview-interest', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('invoice-payments', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('site-generation', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

<?php

namespace App\Http\Controllers\Sites;

use App\Enums\PlanFeature;
use App\Enums\ReviewsStyle;
use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\UpdateLeadProfileRequest;
use App\Http\Requests\Sites\UpdateSiteRequest;
use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\Site;
use App\Models\SiteChatMessage;
use App\Models\SiteDomain;
use App\Models\SitePost;
use App\Models\SiteVersion;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use App\Services\Domains\DomainVerifier;
use App\Services\Preview\PreviewLink;
use App\Services\SiteBuilder\SiteContentSchema;
use App\Services\SiteBuilder\SiteCopy;
use App\Services\SiteBuilder\SiteDesign;
use App\Services\SiteBuilder\SitePages;
use App\Services\SiteBuilder\SiteQualityChecker;
use App\Services\SiteBuilder\SiteStaging;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    /**
     * Display the team's generated websites.
     */
    public function index(Team $currentTeam): Response
    {
        return Inertia::render('sites/Index', [
            'sites' => $currentTeam->sites()
                ->with('lead:id,name,category,city,status')
                ->latest()
                ->orderByDesc('id')
                ->paginate(24)
                ->through(fn (Site $site) => [
                    'id' => $site->id,
                    'name' => $site->lead->name,
                    'category' => $site->lead->category,
                    'city' => $site->lead->city,
                    'leadStatus' => $site->lead->status->value,
                    'template' => $site->template->label(),
                    'accent' => $site->content['accent'],
                    'headline' => $site->content['headline'],
                    'previewUrl' => PreviewLink::page($site),
                    'viewsCount' => $site->views_count,
                    'lastViewedAt' => $site->last_viewed_at?->toISOString(),
                ]),
        ]);
    }

    /**
     * Show the website editor.
     */
    public function edit(Team $currentTeam, Site $site, SiteQualityChecker $quality, DomainVerifier $verifier, UsageMeter $usage): Response
    {
        $primaryDomain = $site->primaryDomain();
        $site->load('lead:id,name,category,city,phone,email,website,description,notes,opening_hours,photo_url,photo_paths,logo_path,site_generation_status,reviews_count,reviews_fetched_at,reviews_fetch_status,reviews_fetch_error');

        return Inertia::render('sites/Edit', [
            'site' => [
                'id' => $site->id,
                'name' => $site->lead->name,
                'leadCategory' => $site->lead->category,
                'leadCity' => $site->lead->city,
                'leadId' => $site->lead->id,
                'leadWebsite' => $site->lead->website,
                'template' => $site->template->value,
                'content' => [
                    ...$site->content,
                    'sections' => $site->content['sections'] ?? SiteSection::defaultOrder(),
                    'pages' => $site->content['pages'] ?? SitePages::OPTIONAL,
                ],
                'previewUrl' => PreviewLink::page($site),
                'editorUrl' => route('sites.preview', $site->slug, false).'?internal=1&editor=1',
                'revision' => $site->revision,
                'media' => [
                    'logo' => $site->lead->logoUrl(),
                    'photos' => $site->lead->photoUrls(),
                    'maxPhotos' => UpdateLeadProfileRequest::MAX_PHOTOS,
                ],
                'viewsCount' => $site->views_count,
                'updatedAt' => $site->updated_at?->toISOString(),
                'generationStatus' => $site->lead->site_generation_status->value,
                'previewExpiresAt' => $site->preview_expires_at?->toDateString(),
                'hasPreviewPassword' => filled($site->preview_password),
                'sectionViews' => collect(PreviewSectionViewController::labels())->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'views' => (int) (($site->preview_section_views ?? [])[$key] ?? 0),
                ])->values(),
                'transferredAt' => $site->transferred_at?->toISOString(),
                'transferredToEmail' => $site->transferred_to_email,
            ],
            'health' => [
                'uptimeOk' => $site->uptime_ok,
                'lastUptimeAt' => $site->last_uptime_at?->toISOString(),
                'lastCrawledAt' => $site->last_crawled_at?->toISOString(),
                'brokenLinks' => $site->brokenLinks()->latest('id')->limit(20)->get(['id', 'url', 'status', 'error']),
                'findings' => $site->delivery_findings ?? [],
                'backupCount' => $site->backups()->count(),
                'comments' => $site->comments()->latest('id')->limit(20)->get()->map(fn ($comment) => [
                    'id' => $comment->id,
                    'author' => $comment->author_name,
                    'section' => $comment->section,
                    'body' => $comment->body,
                    'createdAt' => $comment->created_at->toISOString(),
                ]),
            ],
            'quality' => $quality->check($site),
            'publishing' => [
                'isPublished' => $site->isPublished(),
                'publishedAt' => $site->published_at?->toISOString(),
                'isStagingPublished' => $site->isStagingPublished(),
                'stagingPublishedAt' => $site->staging_published_at?->toISOString(),
                'stagingUrl' => SiteStaging::origin($site),
                'stagingHostname' => SiteStaging::hostname($site),
                'stagingBaseHost' => SiteStaging::baseHost(),
                'liveUrl' => $primaryDomain ? 'https://'.$primaryDomain->hostname : null,
                'canPublish' => $primaryDomain !== null,
                'canPromoteFromStaging' => $site->isStagingPublished() && $primaryDomain !== null,
                'dnsConfigured' => filled(config('sites.cname_target')) || filled(config('sites.server_ip')),
                'domains' => $site->domains()->orderBy('id')->get()->map(fn (SiteDomain $domain) => [
                    'id' => $domain->id,
                    'hostname' => $domain->hostname,
                    'isVerified' => $domain->isVerified(),
                    'isPrimary' => $primaryDomain?->is($domain) ?? false,
                    'error' => $domain->check_error,
                    'lastCheckedAt' => $domain->last_checked_at?->toISOString(),
                    'records' => $verifier->instructions($domain),
                ]),
            ],
            'pageOptions' => collect(SitePages::OPTIONAL)->map(fn (string $page) => [
                'value' => $page,
                'label' => SitePages::PAGE_LABELS[$page],
            ])->values(),
            'posts' => $site->posts()
                ->latest('id')
                ->get()
                ->map(fn (SitePost $post) => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => $post->excerpt,
                    'body' => $post->body,
                    'isPublished' => $post->published_at !== null && $post->published_at->isPast(),
                    'publishedAt' => $post->published_at?->toISOString(),
                ]),
            'versions' => $site->versions()
                ->with('user:id,name')
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (SiteVersion $version) => [
                    'id' => $version->id,
                    'label' => $version->label,
                    'template' => $version->template->label(),
                    'headline' => $version->content['headline'] ?? '',
                    'author' => $version->user?->name,
                    'createdAt' => $version->created_at->toISOString(),
                ]),
            'templates' => SiteTemplate::options(),
            'palettes' => collect(SiteTemplate::cases())->mapWithKeys(fn (SiteTemplate $template) => [$template->value => $template->palette()]),
            'sectionOptions' => SiteSection::options(),
            'designChoices' => SiteDesign::choices(),
            'copyFields' => collect(SiteCopy::FIELDS)->map(fn (array $field, string $key) => ['key' => $key, ...$field])->values(),
            'chat' => $this->chatProps($currentTeam, $site, $usage),
            'reviewsStyles' => ReviewsStyle::options(),
            'reviews' => [
                'items' => $site->lead->reviews()
                    ->orderByDesc('rating')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (LeadReview $review) => [
                        'id' => $review->id,
                        'author' => $review->author_name,
                        'rating' => $review->rating,
                        'body' => $review->body,
                        'isVisible' => $review->is_visible,
                    ]),
                'status' => $site->lead->reviews_fetch_status->value,
                'error' => $site->lead->reviews_fetch_error,
                'fetchedAt' => $site->lead->reviews_fetched_at?->toISOString(),
                'totalOnGoogle' => $site->lead->reviews_count,
                'minimum' => Lead::MIN_TESTIMONIALS,
                'canFetch' => filled(config('services.mindcase.key')),
            ],
        ]);
    }

    /**
     * Save edits to the website content.
     */
    public function update(UpdateSiteRequest $request, Team $currentTeam, Site $site): RedirectResponse
    {
        $template = $request->validated('template');
        $content = SiteContentSchema::preserveEditorFields($site->content, SiteContentSchema::toContent($request->validated()));

        if ($site->template->value !== $template || $site->content != $content) {
            $site->snapshot($request->user()->id);

            $site->update(['template' => $template, 'content' => $content]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Website saved.')]);

        return back();
    }

    /**
     * The AI editing conversation shown on the website editor.
     *
     * @return array<string, mixed>
     */
    protected function chatProps(Team $team, Site $site, UsageMeter $usage): array
    {
        $site->chatMessages()
            ->where('status', SiteChatMessage::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(SiteChatMessage::STALE_AFTER_MINUTES))
            ->update([
                'status' => SiteChatMessage::STATUS_FAILED,
                'body' => __('The edit timed out. Try again.'),
            ]);

        return [
            'available' => $usage->allows($team, PlanFeature::AiEditing),
            'busy' => $site->chatMessages()->where('status', SiteChatMessage::STATUS_PENDING)->exists(),
            'messages' => $site->chatMessages()->latest('id')->limit(20)->get()->reverse()->values()->map(fn (SiteChatMessage $message) => [
                'id' => $message->id,
                'role' => $message->role,
                'body' => $message->body,
                'status' => $message->status,
                'at' => $message->created_at->toISOString(),
            ]),
        ];
    }
}

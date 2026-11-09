<?php

namespace App\Http\Controllers\Sites;

use App\Enums\LeadActivityType;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Team;
use App\Services\Preview\PreviewLink;
use Inertia\Inertia;
use Inertia\Response;

class SiteAnalyticsController extends Controller
{
    /**
     * Preview engagement for one site: views, sections, interest, and comments.
     */
    public function __invoke(Team $currentTeam, Site $site): Response
    {
        abort_unless($site->team_id === $currentTeam->id, 404);

        $site->load('lead:id,team_id,name');

        $sectionViews = collect(PreviewSectionViewController::labels())
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'views' => (int) (($site->preview_section_views ?? [])[$key] ?? 0),
            ])
            ->sortByDesc('views')
            ->values();

        $maxSectionViews = max(1, (int) $sectionViews->max('views'));

        $interestEvents = $site->lead->activities()
            ->whereIn('type', [LeadActivityType::PreviewInterest, LeadActivityType::PreviewClaim])
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn ($activity) => [
                'id' => $activity->id,
                'type' => $activity->type->value,
                'label' => $activity->type === LeadActivityType::PreviewClaim
                    ? __('Claim request')
                    : __('Discuss / interest'),
                'summary' => $activity->summary,
                'name' => $activity->data['name'] ?? null,
                'contact' => $activity->data['contact'] ?? null,
                'message' => $activity->data['message'] ?? null,
                'createdAt' => $activity->created_at?->toISOString(),
            ]);

        $comments = $site->comments()
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn ($comment) => [
                'id' => $comment->id,
                'author' => $comment->author_name,
                'contact' => $comment->author_contact,
                'section' => $comment->section,
                'body' => $comment->body,
                'createdAt' => $comment->created_at?->toISOString(),
            ]);

        return Inertia::render('sites/Analytics', [
            'site' => [
                'id' => $site->id,
                'name' => $site->lead->name,
                'previewUrl' => PreviewLink::page($site),
                'viewsCount' => $site->views_count,
                'lastViewedAt' => $site->last_viewed_at?->toISOString(),
                'interestCount' => $site->lead->activities()
                    ->whereIn('type', [LeadActivityType::PreviewInterest, LeadActivityType::PreviewClaim])
                    ->count(),
                'commentCount' => $site->comments()->count(),
                'claimCount' => $site->lead->activities()
                    ->where('type', LeadActivityType::PreviewClaim)
                    ->count(),
            ],
            'sectionViews' => $sectionViews->map(fn (array $row) => [
                ...$row,
                'share' => (int) round(($row['views'] / $maxSectionViews) * 100),
            ]),
            'interestEvents' => $interestEvents,
            'comments' => $comments,
        ]);
    }
}

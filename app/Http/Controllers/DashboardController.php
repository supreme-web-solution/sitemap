<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\LeadTask;
use App\Models\Site;
use App\Models\Team;
use App\Models\TeamInvitation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Number of days of history sent to the dashboard chart.
     */
    public const CHART_DAYS = 30;

    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $team = $request->user()->currentTeam;

        $wonLeads = $team->leads()->where('status', LeadStatus::Won);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'stats' => [
                'leads' => $team->leads()->count(),
                'sites' => $team->sites()->count(),
                'previewViews' => (int) $team->sites()->sum('views_count'),
                'dealsWon' => (clone $wonLeads)->count(),
                'revenue' => (int) $wonLeads->sum('deal_value'),
            ],
            'followUps' => $this->followUps($team),
            'tasks' => $this->tasks($team),
            'pipeline' => $this->pipeline($team),
            'chart' => $this->chart($team),
            'activity' => $this->activity($team),
            'recentSites' => $team->sites()
                ->with('lead:id,name,category,city,status')
                ->latest()
                ->orderByDesc('id')
                ->limit(6)
                ->get()
                ->map(fn (Site $site) => [
                    'id' => $site->id,
                    'name' => $site->lead->name,
                    'category' => $site->lead->category,
                    'city' => $site->lead->city,
                    'leadStatus' => $site->lead->status->value,
                    'template' => $site->template->label(),
                    'viewsCount' => $site->views_count,
                    'previewUrl' => route('sites.preview', $site->slug),
                    'createdAt' => $site->created_at->toISOString(),
                ]),
        ]);
    }

    /**
     * The leads that need a follow-up soonest, with the total number that are due.
     *
     * @return array{dueCount: int, items: list<array<string, mixed>>}
     */
    protected function followUps(Team $team): array
    {
        $open = $team->leads()
            ->whereNotNull('next_follow_up_at')
            ->whereNotIn('status', [LeadStatus::Won, LeadStatus::Lost]);

        return [
            'dueCount' => (clone $open)->whereDate('next_follow_up_at', '<=', today())->count(),
            'items' => $open
                ->orderBy('next_follow_up_at')
                ->orderBy('id')
                ->limit(6)
                ->get(['id', 'name', 'category', 'city', 'status', 'next_follow_up_at'])
                ->map(fn ($lead) => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'category' => $lead->category,
                    'city' => $lead->city,
                    'status' => $lead->status->value,
                    'dueOn' => $lead->next_follow_up_at->toDateString(),
                    'isOverdue' => $lead->next_follow_up_at->isBefore(today()),
                    'isDueToday' => $lead->next_follow_up_at->isToday(),
                ])
                ->all(),
        ];
    }

    /**
     * Open tasks due soonest, with how many are overdue or due today.
     *
     * @return array{dueCount: int, items: list<array<string, mixed>>}
     */
    protected function tasks(Team $team): array
    {
        $open = $team->leadTasks()->open()->with('lead:id,name');

        return [
            'dueCount' => (clone $open)->whereDate('due_on', '<=', today())->count(),
            'items' => $open
                ->orderByRaw('CASE WHEN due_on IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_on')
                ->orderBy('id')
                ->limit(6)
                ->get()
                ->map(fn (LeadTask $task) => [
                    'id' => $task->id,
                    'leadId' => $task->lead_id,
                    'title' => $task->title,
                    'leadName' => $task->lead->name,
                    'dueOn' => $task->due_on?->toDateString(),
                    'isOverdue' => $task->due_on?->isBefore(today()) ?? false,
                    'isDueToday' => $task->due_on?->isToday() ?? false,
                ])
                ->all(),
        ];
    }

    /**
     * Count the team's leads in every pipeline stage.
     *
     * @return array<string, int>
     */
    protected function pipeline(Team $team): array
    {
        $counts = $team->leads()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(LeadStatus::cases())
            ->mapWithKeys(fn (LeadStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * Daily counts of new leads and generated websites for the last CHART_DAYS days.
     *
     * @return array{days: list<array{date: string, label: string, weekday: string}>, leads: list<int>, sites: list<int>}
     */
    protected function chart(Team $team): array
    {
        $start = now()->subDays(self::CHART_DAYS - 1)->startOfDay();

        $leads = $this->dailyCounts($team->leads(), $start);
        $sites = $this->dailyCounts($team->sites(), $start);

        $days = collect(range(self::CHART_DAYS - 1, 0))
            ->map(fn (int $daysAgo) => now()->subDays($daysAgo));

        return [
            'days' => $days->map(fn (CarbonInterface $day) => [
                'date' => $day->toDateString(),
                'label' => $day->format('M j'),
                'weekday' => $day->format('D'),
            ])->all(),
            'leads' => $days->map(fn (CarbonInterface $day) => (int) ($leads[$day->toDateString()] ?? 0))->all(),
            'sites' => $days->map(fn (CarbonInterface $day) => (int) ($sites[$day->toDateString()] ?? 0))->all(),
        ];
    }

    /**
     * Count rows created per day since the given date.
     *
     * @return Collection<string, int>
     */
    protected function dailyCounts(HasMany $relation, CarbonInterface $start): Collection
    {
        return $relation
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
    }

    /**
     * The most recent things that happened in the team, newest first.
     *
     * @return list<array{type: string, title: string, detail: string|null, at: string}>
     */
    protected function activity(Team $team): array
    {
        $created = $team->sites()->with('lead:id,name')->latest()->orderByDesc('id')->limit(5)->get()
            ->map(fn (Site $site) => [
                'type' => 'site_created',
                'title' => $site->lead->name,
                'detail' => $site->template->label(),
                'at' => $site->created_at,
            ]);

        $viewed = $team->sites()->with('lead:id,name')
            ->whereNotNull('last_viewed_at')
            ->orderByDesc('last_viewed_at')
            ->limit(5)
            ->get()
            ->map(fn (Site $site) => [
                'type' => 'preview_viewed',
                'title' => $site->lead->name,
                'detail' => trans_choice(':count view in total|:count views in total', $site->views_count),
                'at' => $site->last_viewed_at,
            ]);

        $found = $team->leads()->latest()->orderByDesc('id')->limit(5)->get()
            ->map(fn ($lead) => [
                'type' => 'lead_found',
                'title' => $lead->name,
                'detail' => $lead->category ?? $lead->city,
                'at' => $lead->created_at,
            ]);

        $won = $team->leads()->where('status', LeadStatus::Won)->orderByDesc('updated_at')->limit(3)->get()
            ->map(fn ($lead) => [
                'type' => 'deal_won',
                'title' => $lead->name,
                'detail' => $lead->deal_value ? '$'.number_format((float) $lead->deal_value) : null,
                'at' => $lead->updated_at,
            ]);

        return $created->concat($viewed)->concat($found)->concat($won)
            ->sortByDesc('at')
            ->take(6)
            ->map(fn (array $event) => [...$event, 'at' => $event['at']->toISOString()])
            ->values()
            ->all();
    }
}

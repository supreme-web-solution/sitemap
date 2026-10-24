<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\LeadBoardRequest;
use App\Http\Resources\LeadResource;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class LeadBoardController extends Controller
{
    /**
     * The number of cards a column loads first, and how many each "show more" adds.
     */
    public const CARDS_PER_COLUMN = 15;

    /**
     * Show the pipeline as a kanban board.
     */
    public function __invoke(LeadBoardRequest $request, Team $currentTeam): Response
    {
        $filters = $request->filters();
        $limits = $request->limits();

        $matching = fn (): Builder => $currentTeam->leads()
            ->getQuery()
            ->filteredBy($filters)
            ->when(isset($filters['ideal']), fn (Builder $query) => $currentTeam->applyIdealCustomerFilter($query));

        $counts = $matching()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $columns = collect(LeadStatus::cases())->map(fn (LeadStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
            'leads' => LeadResource::collection(
                $matching()
                    ->with(['site:id,lead_id,team_id,slug', 'site.team:id,preview_hostname,preview_hostname_verified_at'])
                    ->where('status', $status)
                    ->sortedBy($filters['sort'] ?? null)
                    ->limit($limits[$status->value] ?? self::CARDS_PER_COLUMN)
                    ->get(),
            )->resolve(),
        ])->values();

        return Inertia::render('leads/Board', [
            'columns' => $columns,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'tier' => $filters['tier'] ?? null,
                'sort' => $filters['sort'] ?? 'score',
                'follow_up' => $filters['follow_up'] ?? null,
                'assigned' => $filters['assigned'] ?? null,
                'ideal' => $filters['ideal'] ?? null,
            ],
            'limits' => $limits,
            'step' => self::CARDS_PER_COLUMN,
            'totalLeads' => $currentTeam->leads()->count(),
            'members' => $currentTeam->memberOptions(),
            'hasIdealCustomer' => filled($currentTeam->ideal_customer),
        ]);
    }
}

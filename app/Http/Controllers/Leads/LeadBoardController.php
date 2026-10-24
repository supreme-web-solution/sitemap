<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadResource;
use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class LeadBoardController extends Controller
{
    /**
     * The number of cards loaded into each board column.
     */
    protected const CARDS_PER_COLUMN = 30;

    /**
     * Show the pipeline as a kanban board.
     */
    public function __invoke(Team $currentTeam): Response
    {
        $counts = $currentTeam->leads()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $columns = collect(LeadStatus::cases())->map(fn (LeadStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
            'leads' => LeadResource::collection(
                $currentTeam->leads()
                    ->with('site:id,lead_id,team_id,slug')
                    ->where('status', $status)
                    ->sortedBy('score')
                    ->limit(self::CARDS_PER_COLUMN)
                    ->get(),
            )->resolve(),
        ])->values();

        return Inertia::render('leads/Board', ['columns' => $columns]);
    }
}

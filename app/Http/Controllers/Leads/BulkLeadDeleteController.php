<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Leads\DeleteLead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\BulkDeleteLeadsRequest;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BulkLeadDeleteController extends Controller
{
    /**
     * Permanently delete many leads and everything saved for each of them.
     */
    public function __invoke(BulkDeleteLeadsRequest $request, Team $currentTeam, DeleteLead $deleteLead): RedirectResponse
    {
        $leadIds = $request->validated('lead_ids');

        $leads = Lead::query()
            ->where('team_id', $currentTeam->id)
            ->whereIn('id', $leadIds)
            ->get();

        foreach ($leads as $lead) {
            $deleteLead->handle($lead);
        }

        $count = $leads->count();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                'Deleted :count lead and everything saved for it.|Deleted :count leads and everything saved for them.',
                $count,
                ['count' => $count],
            ),
        ]);

        return back();
    }
}

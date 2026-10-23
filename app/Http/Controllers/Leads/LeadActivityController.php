<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreLeadNoteRequest;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;

class LeadActivityController extends Controller
{
    /**
     * Add a note to the lead's timeline.
     */
    public function store(StoreLeadNoteRequest $request, Team $currentTeam, Lead $lead): RedirectResponse
    {
        $lead->recordActivity(
            LeadActivityType::Note,
            $request->validated('note'),
            null,
            $request->user()->id,
        );

        return back();
    }
}

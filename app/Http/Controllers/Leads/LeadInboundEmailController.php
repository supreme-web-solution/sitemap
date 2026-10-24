<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\LogInboundEmailRequest;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Mail\InboundMailProcessor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadInboundEmailController extends Controller
{
    /**
     * Log a prospect reply on the lead timeline when inbox sync is unavailable.
     */
    public function store(
        LogInboundEmailRequest $request,
        Team $currentTeam,
        Lead $lead,
        InboundMailProcessor $processor,
    ): RedirectResponse {
        $processor->recordManual(
            $lead,
            $request->validated('subject'),
            $request->validated('snippet'),
            $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reply logged on the timeline.')]);

        return back();
    }
}

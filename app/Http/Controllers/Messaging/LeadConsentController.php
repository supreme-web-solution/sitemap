<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadConsentController extends Controller
{
    /**
     * Record that the contact agreed to receive texts or WhatsApp messages.
     */
    public function store(Request $request, Team $currentTeam, Lead $lead): RedirectResponse
    {
        $request->validate(['confirmed' => ['accepted']]);

        if ($lead->messaging_opted_out_at !== null) {
            throw ValidationException::withMessages(['confirmed' => __('This contact opted out. Only they can opt back in by replying START.')]);
        }

        $lead->forceFill(['messaging_consent_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Consent recorded.')]);

        return back();
    }

    /**
     * Withdraw consent on the contact's behalf.
     */
    public function destroy(Team $currentTeam, Lead $lead): RedirectResponse
    {
        $lead->forceFill(['messaging_consent_at' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Consent removed.')]);

        return back();
    }
}

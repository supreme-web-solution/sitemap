<?php

namespace App\Http\Controllers\Leads;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\UsageMetric;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\SendOutreachRequest;
use App\Mail\OutreachEmail;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Billing\UsageMeter;
use App\Services\Mail\OutboundThreadRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadOutreachController extends Controller
{
    /**
     * Send an email to the lead, or log that you contacted them another way.
     */
    public function store(SendOutreachRequest $request, Team $currentTeam, Lead $lead, UsageMeter $usage, OutboundThreadRecorder $threads): RedirectResponse
    {
        $user = $request->user();
        $sending = $request->validated('mode') === 'send';

        if ($sending && blank($lead->email)) {
            throw ValidationException::withMessages([
                'mode' => __('This lead has no email address. Add one, or log the contact instead.'),
            ]);
        }

        if ($sending) {
            $usage->consume($currentTeam, UsageMetric::Emails);

            $subject = $request->validated('subject');
            $messageId = $threads->generateMessageId();
            $threads->record($lead, $subject, $messageId);

            Mail::to($lead->email)->queue(new OutreachEmail(
                $subject,
                $request->validated('body'),
                $user->name,
                $user->email,
                $messageId,
            ));
        }

        $lead->recordActivity(
            $sending ? LeadActivityType::EmailSent : LeadActivityType::MarkedContacted,
            $sending
                ? __('Email sent: :subject', ['subject' => $request->validated('subject')])
                : __('Contacted outside the app: :subject', ['subject' => $request->validated('subject')]),
            ['subject' => $request->validated('subject'), 'body' => $request->validated('body')],
            $user->id,
        );

        $lead->update([
            'status' => in_array($lead->status, [LeadStatus::New, LeadStatus::SiteGenerated], true)
                ? LeadStatus::Contacted
                : $lead->status,
            'last_contacted_at' => now(),
            'next_follow_up_at' => today()->addDays((int) config('prospecting.follow_up_days_after_contact')),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $sending
            ? __('Email queued. A follow-up is set for :date.', ['date' => $lead->next_follow_up_at->toFormattedDateString()])
            : __('Contact logged. A follow-up is set for :date.', ['date' => $lead->next_follow_up_at->toFormattedDateString()])]);

        return back();
    }
}

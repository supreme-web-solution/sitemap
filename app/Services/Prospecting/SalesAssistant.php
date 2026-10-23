<?php

namespace App\Services\Prospecting;

use App\Enums\LeadStatus;
use App\Models\Lead;

class SalesAssistant
{
    /**
     * Next useful actions for this lead, based on stage and what is already on file.
     *
     * @return list<array{title: string, detail: string}>
     */
    public function suggest(Lead $lead): array
    {
        $suggestions = [];

        if ($lead->site === null) {
            $suggestions[] = ['title' => 'Build a preview website', 'detail' => 'A shareable preview is the fastest way to start a conversation.'];
        } elseif ($lead->status === LeadStatus::New || $lead->status === LeadStatus::SiteGenerated) {
            $suggestions[] = ['title' => 'Send the preview', 'detail' => 'Email or text the preview link, then enroll them in a follow-up sequence.'];
        }

        if ($lead->status === LeadStatus::Contacted && $lead->proposals()->doesntExist()) {
            $suggestions[] = ['title' => 'Send a proposal', 'detail' => 'Turn interest into a package with a public accept button.'];
        }

        if ($lead->status === LeadStatus::Interested && $lead->invoices()->doesntExist()) {
            $suggestions[] = ['title' => 'Issue an invoice or deposit', 'detail' => 'A deposit invoice often closes the deal while you finish the site.'];
        }

        if ($lead->assigned_user_id === null) {
            $suggestions[] = ['title' => 'Assign an owner', 'detail' => 'Someone on the team should own the next conversation.'];
        }

        if ($lead->next_follow_up_at === null && ! in_array($lead->status, [LeadStatus::Won, LeadStatus::Lost], true)) {
            $suggestions[] = ['title' => 'Set a follow-up date', 'detail' => 'Leads without a date disappear from the dashboard list.'];
        }

        if ($suggestions === []) {
            $suggestions[] = ['title' => 'Keep the timeline current', 'detail' => 'Log the last conversation so anyone on the team can pick this up.'];
        }

        return array_slice($suggestions, 0, 3);
    }
}

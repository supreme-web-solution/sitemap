<?php

namespace App\Services\CommandCenter;

use App\Enums\CommandCenterMessageSource;
use App\Enums\LeadSearchStatus;
use App\Models\CommandCenterMessage;
use App\Models\LeadSearch;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;

/**
 * Posts follow-up updates from the assistant once queued or approved work has finished.
 *
 * Updates land in the shared Command Center thread and, when the person last spoke to
 * the assistant over WhatsApp, are also delivered there.
 */
class CommandCenterNotifier
{
    public function __construct(
        protected CommandCenterChatLog $chatLog,
        protected CommandCenterWhatsAppResponder $whatsapp,
    ) {}

    public function notify(Team $team, User $user, string $body): void
    {
        $source = $this->activeSource($team, $user);

        $this->chatLog->appendAssistant($team, $user, $body, $source);

        if ($source !== CommandCenterMessageSource::WhatsApp) {
            return;
        }

        $membership = Membership::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->first();

        if ($membership !== null) {
            $this->whatsapp->sendReply($membership, $body);
        }
    }

    public function actionApproved(Team $team, User $user, string $result): void
    {
        $this->notify($team, $user, __('Approved — :result', ['result' => $result]));
    }

    public function hasWorkInProgress(Team $team, User $user): bool
    {
        return LeadSearch::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('notify_command_center', true)
            ->whereIn('status', [LeadSearchStatus::Pending, LeadSearchStatus::Running])
            ->exists();
    }

    public function leadSearchFinished(LeadSearch $search): void
    {
        if (! $search->notify_command_center || $search->user_id === null) {
            return;
        }

        $team = $search->team;
        $user = $search->user;

        if ($team === null || $user === null) {
            return;
        }

        $label = $this->searchLabel($search);

        if ($search->status === LeadSearchStatus::Failed) {
            $this->notify($team, $user, __('The search for :label could not be completed: :error', [
                'label' => $label,
                'error' => $search->error ?? __('Something went wrong.'),
            ]));

            return;
        }

        $saved = $search->leads()->count();

        $this->notify($team, $user, $saved > 0
            ? __('Your search for :label is complete — :count new lead(s) were added to your pipeline.', [
                'label' => $label,
                'count' => $saved,
            ])
            : __('Your search for :label is complete, but no new leads matched (they may already be in your pipeline or have been filtered out).', [
                'label' => $label,
            ]));
    }

    /**
     * @param  list<string>  $actions
     */
    public function workflowFinished(Team $team, User $user, string $leadName, array $actions): void
    {
        $this->notify($team, $user, $actions === []
            ? __('I checked :name, but there was nothing to run for that automation.', ['name' => $leadName])
            : __('Automation finished for :name: :actions.', [
                'name' => $leadName,
                'actions' => implode('; ', $actions),
            ]));
    }

    protected function activeSource(Team $team, User $user): CommandCenterMessageSource
    {
        $latest = CommandCenterMessage::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('role', 'user')
            ->latest('id')
            ->first();

        return $latest?->source ?? CommandCenterMessageSource::Web;
    }

    protected function searchLabel(LeadSearch $search): string
    {
        $keyword = $search->keyword;
        $location = $search->location;

        if (filled($keyword) && filled($location)) {
            return "“{$keyword}” in {$location}";
        }

        return filled($keyword) ? "“{$keyword}”" : __('your area');
    }
}

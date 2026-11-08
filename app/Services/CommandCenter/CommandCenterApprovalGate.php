<?php

namespace App\Services\CommandCenter;

use App\Enums\CommandCenterPendingActionStatus;
use App\Models\CommandCenterPendingAction;
use App\Support\CommandCenter\CommandCenterContext;

class CommandCenterApprovalGate
{
    /** @var list<string> */
    public const DESTRUCTIVE_ACTIONS = [
        'delete_lead',
        'bulk_delete_leads',
    ];

    /** @var list<string> */
    public const MUTATING_ACTIONS = [
        'start_lead_search',
        'run_lead_automation',
        'update_lead_status',
        'update_lead_details',
        'bulk_update_lead_status',
        'enroll_lead_sequence',
        'delete_lead',
        'bulk_delete_leads',
    ];

    public function needsApproval(CommandCenterContext $context, string $action): bool
    {
        if (in_array($action, self::DESTRUCTIVE_ACTIONS, true)) {
            return true;
        }

        if (! $context->autopilot()) {
            return in_array($action, self::MUTATING_ACTIONS, true);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function propose(
        CommandCenterContext $context,
        string $action,
        array $payload,
        string $summary,
    ): CommandCenterPendingAction {
        return CommandCenterPendingAction::query()->create([
            'team_id' => $context->team->id,
            'user_id' => $context->user->id,
            'action' => $action,
            'summary' => $summary,
            'payload' => $payload,
            'status' => CommandCenterPendingActionStatus::Pending,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function runOrPropose(
        CommandCenterContext $context,
        string $action,
        array $payload,
        string $summary,
        callable $execute,
    ): string {
        if ($this->needsApproval($context, $action)) {
            $pending = $this->propose($context, $action, $payload, $summary);

            return __('Approval #:id queued: :summary. Ask the user to tap Approve above the message box in the Command Center. Once approved, the app posts a completion update in this chat (and on WhatsApp) automatically.', [
                'id' => $pending->id,
                'summary' => $summary,
            ]);
        }

        return $execute();
    }
}

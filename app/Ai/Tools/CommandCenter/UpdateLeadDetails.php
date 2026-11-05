<?php

namespace App\Ai\Tools\CommandCenter;

use App\Ai\Tools\CommandCenter\Concerns\InteractsWithCommandCenterActions;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateLeadDetails implements Tool
{
    use InteractsWithCommandCenterActions;

    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Update deal value, notes, follow-up date, or assignee for a lead.';
    }

    public function handle(Request $request): Stringable|string
    {
        $leadId = (int) ($request['lead_id'] ?? 0);

        if ($leadId < 1) {
            return 'lead_id is required.';
        }

        $payload = array_filter([
            'lead_id' => $leadId,
            'deal_value' => isset($request['deal_value']) ? (int) $request['deal_value'] : null,
            'notes' => $request['notes'] ?? null,
            'assigned_user_id' => isset($request['assigned_user_id']) ? (int) $request['assigned_user_id'] : null,
            'next_follow_up_at' => $request['next_follow_up_at'] ?? null,
        ], fn ($value) => $value !== null);

        if (count($payload) < 2) {
            return 'Provide at least one field to update besides lead_id.';
        }

        return $this->runOrPropose(
            $this->context,
            'update_lead_details',
            $payload,
            __('Update details for lead #:id', ['id' => $leadId]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->required(),
            'deal_value' => $schema->integer()->nullable(),
            'notes' => $schema->string()->nullable(),
            'assigned_user_id' => $schema->integer()->nullable(),
            'next_follow_up_at' => $schema->string()->nullable(),
        ];
    }
}

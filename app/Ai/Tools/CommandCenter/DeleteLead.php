<?php

namespace App\Ai\Tools\CommandCenter;

use App\Ai\Tools\CommandCenter\Concerns\InteractsWithCommandCenterActions;
use App\Models\Lead;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteLead implements Tool
{
    use InteractsWithCommandCenterActions;

    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Permanently delete one lead. Always requires user approval above the message box.';
    }

    public function handle(Request $request): Stringable|string
    {
        $leadId = (int) ($request['lead_id'] ?? 0);

        if ($leadId < 1) {
            return 'lead_id is required.';
        }

        $lead = Lead::query()
            ->where('team_id', $this->context->team->id)
            ->whereKey($leadId)
            ->first(['id', 'name']);

        $label = $lead !== null
            ? __('Delete lead #:id :name', ['id' => $leadId, 'name' => $lead->name])
            : __('Delete lead #:id', ['id' => $leadId]);

        return $this->runOrPropose(
            $this->context,
            'delete_lead',
            ['lead_id' => $leadId],
            $label,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->required(),
        ];
    }
}

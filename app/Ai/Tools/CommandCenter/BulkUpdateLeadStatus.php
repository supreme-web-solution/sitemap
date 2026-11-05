<?php

namespace App\Ai\Tools\CommandCenter;

use App\Ai\Tools\CommandCenter\Concerns\InteractsWithCommandCenterActions;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class BulkUpdateLeadStatus implements Tool
{
    use InteractsWithCommandCenterActions;

    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Move up to 50 leads to the same pipeline stage by id list.';
    }

    public function handle(Request $request): Stringable|string
    {
        $leadIds = $request['lead_ids'] ?? [];
        $status = (string) ($request['status'] ?? '');

        if (! is_array($leadIds) || $leadIds === [] || $status === '') {
            return 'lead_ids and status are required.';
        }

        $leadIds = array_values(array_map(intval(...), $leadIds));

        $payload = ['lead_ids' => $leadIds, 'status' => $status];

        return $this->runOrPropose(
            $this->context,
            'bulk_update_lead_status',
            $payload,
            __('Move :count leads to :status', ['count' => count($leadIds), 'status' => $status]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_ids' => $schema->array()->required()->items($schema->integer()),
            'status' => $schema->string()->required(),
        ];
    }
}

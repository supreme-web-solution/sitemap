<?php

namespace App\Ai\Tools\CommandCenter;

use App\Ai\Tools\CommandCenter\Concerns\InteractsWithCommandCenterActions;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class BulkDeleteLeads implements Tool
{
    use InteractsWithCommandCenterActions;

    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Permanently delete up to 50 leads by id. Always requires user approval.';
    }

    public function handle(Request $request): Stringable|string
    {
        $leadIds = $request['lead_ids'] ?? [];

        if (! is_array($leadIds) || $leadIds === []) {
            return 'lead_ids is required.';
        }

        $leadIds = array_values(array_map(intval(...), $leadIds));

        return $this->runOrPropose(
            $this->context,
            'bulk_delete_leads',
            ['lead_ids' => $leadIds],
            __('Delete :count leads permanently', ['count' => count($leadIds)]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_ids' => $schema->array()->required()->items($schema->integer()),
        ];
    }
}

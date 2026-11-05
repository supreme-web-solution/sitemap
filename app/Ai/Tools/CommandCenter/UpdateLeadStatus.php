<?php

namespace App\Ai\Tools\CommandCenter;

use App\Ai\Tools\CommandCenter\Concerns\InteractsWithCommandCenterActions;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateLeadStatus implements Tool
{
    use InteractsWithCommandCenterActions;

    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Move one lead to a pipeline stage (new, site_generated, contacted, interested, won, lost).';
    }

    public function handle(Request $request): Stringable|string
    {
        $leadId = (int) ($request['lead_id'] ?? 0);
        $status = (string) ($request['status'] ?? '');

        if ($leadId < 1 || $status === '') {
            return 'lead_id and status are required.';
        }

        $payload = [
            'lead_id' => $leadId,
            'status' => $status,
            'lost_reason' => $request['lost_reason'] ?? null,
        ];

        return $this->runOrPropose(
            $this->context,
            'update_lead_status',
            $payload,
            __('Set lead #:id to :status', ['id' => $leadId, 'status' => $status]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->required(),
            'status' => $schema->string()->required(),
            'lost_reason' => $schema->string()->nullable(),
        ];
    }
}

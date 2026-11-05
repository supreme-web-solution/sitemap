<?php

namespace App\Ai\Tools\CommandCenter;

use App\Ai\Tools\CommandCenter\Concerns\InteractsWithCommandCenterActions;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EnrollLeadInSequence implements Tool
{
    use InteractsWithCommandCenterActions;

    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Enroll a lead in a follow-up sequence (uses default sequence when sequence_id omitted).';
    }

    public function handle(Request $request): Stringable|string
    {
        $leadId = (int) ($request['lead_id'] ?? 0);

        if ($leadId < 1) {
            return 'lead_id is required.';
        }

        $payload = [
            'lead_id' => $leadId,
            'sequence_id' => isset($request['sequence_id']) ? (int) $request['sequence_id'] : null,
        ];

        return $this->runOrPropose(
            $this->context,
            'enroll_lead_sequence',
            $payload,
            __('Enroll lead #:id in outreach sequence', ['id' => $leadId]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->required(),
            'sequence_id' => $schema->integer()->nullable(),
        ];
    }
}

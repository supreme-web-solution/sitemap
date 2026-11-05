<?php

namespace App\Ai\Tools\CommandCenter;

use App\Ai\Tools\CommandCenter\Concerns\InteractsWithCommandCenterActions;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RunLeadAutomation implements Tool
{
    use InteractsWithCommandCenterActions;

    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Queue automation steps for a lead (audit, site generation, sequence enroll, draft proposal, or full_playbook).';
    }

    public function handle(Request $request): Stringable|string
    {
        $leadId = (int) ($request['lead_id'] ?? 0);
        $steps = $request['steps'] ?? [];

        if ($leadId < 1 || ! is_array($steps) || $steps === []) {
            return 'lead_id and steps are required.';
        }

        $allowed = ['full_playbook', 'enroll_sequence', 'audit', 'generate_site', 'draft_proposal'];
        $steps = array_values(array_intersect($allowed, array_map(strval(...), $steps)));

        if ($steps === []) {
            return 'No valid steps provided.';
        }

        $payload = ['lead_id' => $leadId, 'steps' => $steps];

        return $this->runOrPropose(
            $this->context,
            'run_lead_automation',
            $payload,
            __('Run :steps on lead #:id', ['steps' => implode(', ', $steps), 'id' => $leadId]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lead_id' => $schema->integer()->required(),
            'steps' => $schema->array()->required()->items(
                $schema->string()->enum(['full_playbook', 'enroll_sequence', 'audit', 'generate_site', 'draft_proposal']),
            ),
        ];
    }
}

<?php

namespace App\Ai\Tools\CommandCenter;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PipelineSummary implements Tool
{
    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Summarize lead counts by pipeline stage for the current team.';
    }

    public function handle(Request $request): Stringable|string
    {
        $counts = Lead::query()
            ->where('team_id', $this->context->team->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $lines = collect(LeadStatus::cases())->map(function (LeadStatus $status) use ($counts) {
            return $status->value.': '.(int) ($counts[$status->value] ?? $counts[$status->name] ?? 0);
        });

        $total = (int) $counts->sum();

        return "Pipeline ({$total} leads total):\n".$lines->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

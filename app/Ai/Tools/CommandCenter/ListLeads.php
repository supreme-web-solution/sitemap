<?php

namespace App\Ai\Tools\CommandCenter;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListLeads implements Tool
{
    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'List leads for the current team with optional text search and pipeline stage filter.';
    }

    public function handle(Request $request): Stringable|string
    {
        $status = filled($request['status'] ?? null)
            ? LeadStatus::tryFrom((string) $request['status'])
            : null;

        if (filled($request['status'] ?? null) && $status === null) {
            return 'Invalid status. Use: new, site_generated, contacted, interested, won, lost.';
        }

        $query = Lead::query()
            ->where('team_id', $this->context->team->id)
            ->orderByDesc('id');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if (filled($request['search'] ?? null)) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request['search']).'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhere('city', 'like', $term)
                    ->orWhere('category', 'like', $term);
            });
        }

        $limit = min(25, max(1, (int) ($request['limit'] ?? 10)));

        $leads = $query->limit($limit)->get(['id', 'name', 'city', 'status', 'email', 'website']);

        if ($leads->isEmpty()) {
            return 'No leads matched.';
        }

        $lines = $leads->map(fn (Lead $lead) => sprintf(
            '#%d %s — %s — %s%s',
            $lead->id,
            $lead->name,
            $lead->status->value,
            $lead->city ?? 'no city',
            filled($lead->website) ? ' — has website' : '',
        ));

        return "Leads (max {$limit}):\n".$lines->implode("\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->nullable(),
            'status' => $schema->string()->nullable(),
            'limit' => $schema->integer()->min(1)->max(25)->nullable(),
        ];
    }
}

<?php

namespace App\Ai\Tools\CommandCenter;

use App\Ai\Tools\CommandCenter\Concerns\InteractsWithCommandCenterActions;
use App\Enums\WebsiteFilter;
use App\Support\CommandCenter\CommandCenterContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class StartLeadSearch implements Tool
{
    use InteractsWithCommandCenterActions;

    public function __construct(protected CommandCenterContext $context) {}

    public function description(): Stringable|string
    {
        return 'Start a Google Maps prospecting search for the team (uses plan lead-search quota).';
    }

    public function handle(Request $request): Stringable|string
    {
        $keyword = trim((string) ($request['keyword'] ?? ''));
        $location = trim((string) ($request['location'] ?? ''));

        if ($keyword === '' || $location === '') {
            return 'keyword and location are required.';
        }

        $payload = [
            'keyword' => $keyword,
            'location' => $location,
            'website_filter' => (string) ($request['website_filter'] ?? WebsiteFilter::WithoutWebsite->value),
            'minimum_rating' => $request['minimum_rating'] ?? null,
            'max_results' => (int) ($request['max_results'] ?? 20),
        ];

        return $this->runOrPropose(
            $this->context,
            'start_lead_search',
            $payload,
            __('Search “:keyword” in :location', ['keyword' => $keyword, 'location' => $location]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()->required(),
            'location' => $schema->string()->required(),
            'website_filter' => $schema->string()->nullable(),
            'minimum_rating' => $schema->number()->nullable(),
            'max_results' => $schema->integer()->min(1)->max(100)->nullable(),
        ];
    }
}

<?php

namespace App\Ai\Tools\CommandCenter;

use App\Ai\Tools\CommandCenter\Concerns\InteractsWithCommandCenterActions;
use App\Enums\WebsiteFilter;
use App\Support\CommandCenter\CommandCenterContext;
use App\Support\LeadSearchLocation;
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
        return 'Start a Google Maps prospecting search for the team (uses plan lead-search quota). location must be a city, such as "Dallas, Texas". Never pass a street address; if the user pastes one, pass only the city and region from it.';
    }

    public function handle(Request $request): Stringable|string
    {
        $keyword = trim((string) ($request['keyword'] ?? ''));
        $location = trim((string) ($request['location'] ?? ''));

        if ($keyword === '' || $location === '') {
            return 'keyword and location are required.';
        }

        $resolved = LeadSearchLocation::forSearch($location);

        if (! $resolved['ok']) {
            return 'Do not start a search. '.$resolved['notice'];
        }

        $location = $resolved['location'];

        $payload = [
            'keyword' => $keyword,
            'location' => $location,
            'website_filter' => (string) ($request['website_filter'] ?? WebsiteFilter::WithoutWebsite->value),
            'minimum_rating' => $request['minimum_rating'] ?? null,
            'max_results' => (int) ($request['max_results'] ?? 20),
        ];

        $summary = __('Search “:keyword” in :location', ['keyword' => $keyword, 'location' => $location]);

        if ($resolved['notice'] !== null) {
            $summary .= ' '.$resolved['notice'];
        }

        return $this->runOrPropose(
            $this->context,
            'start_lead_search',
            $payload,
            $summary,
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

<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use App\Enums\SiteSection;
use App\Services\SiteBuilder\CustomHomeSection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddHomepageSection implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'Insert a catalog section (services, reviews, …) or custom:block-id into the homepage order.';
    }

    public function handle(Request $request): Stringable|string
    {
        $section = trim((string) ($request['section'] ?? ''));

        if ($section === '') {
            return 'Rejected: section required.';
        }

        if (! SiteSection::tryFrom($section) && ! CustomHomeSection::isKey($section)) {
            return 'Rejected: unknown section key.';
        }

        $this->state->sectionsAdd[] = $section;

        return 'Accepted: will add '.$section.' to the homepage.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section' => $schema->string()->required(),
        ];
    }
}

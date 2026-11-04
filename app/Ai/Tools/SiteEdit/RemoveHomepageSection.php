<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use App\Enums\SiteSection;
use App\Services\SiteBuilder\CustomHomeSection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RemoveHomepageSection implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'Remove a section from the homepage order.';
    }

    public function handle(Request $request): Stringable|string
    {
        $section = trim((string) ($request['section'] ?? ''));

        if (! SiteSection::tryFrom($section) && ! CustomHomeSection::isKey($section)) {
            return 'Rejected: unknown section key.';
        }

        $this->state->sectionsRemove[] = $section;

        return 'Accepted: will remove '.$section;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section' => $schema->string()->required(),
        ];
    }
}

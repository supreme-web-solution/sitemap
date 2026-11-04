<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use App\Services\SiteBuilder\SectionVariants;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListSectionLayoutVariants implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'List layout preset ids for a section (hero overlay, services list, …). Set with path variants.<section>.';
    }

    public function handle(Request $request): Stringable|string
    {
        $section = trim((string) ($request['section'] ?? ''));

        if (! isset(SectionVariants::CATALOG[$section])) {
            return 'Unknown section for layout presets.';
        }

        $definition = SectionVariants::CATALOG[$section];
        $lines = ["Default: {$definition['default']}"];

        foreach ($definition['variants'] as $id => $label) {
            $lines[] = "- {$id}: {$label}";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section' => $schema->string()->required(),
        ];
    }
}

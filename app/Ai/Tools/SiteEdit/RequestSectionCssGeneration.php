<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use App\Services\SiteBuilder\SiteSectionCss;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RequestSectionCssGeneration implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'Ask the CSS specialist to generate scoped CSS when you cannot write section_css yourself. Prefer set_document_field when you know the CSS.';
    }

    public function handle(Request $request): Stringable|string
    {
        $section = trim((string) ($request['section'] ?? ''));
        $note = trim((string) ($request['instruction'] ?? ''));

        if (! isset(SiteSectionCss::TARGETS[$section])) {
            return 'Rejected: not a CSS target section.';
        }

        $this->state->designCssTargets[] = $section;

        if ($note !== '') {
            $this->state->designInstruction = trim($this->state->designInstruction."\n".$section.': '.$note);
        }

        return 'Accepted CSS generation for '.$section;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section' => $schema->string()->required(),
            'instruction' => $schema->string()->description('Brief for the CSS designer'),
        ];
    }
}

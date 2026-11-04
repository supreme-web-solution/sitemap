<?php

namespace App\Ai\Tools;

use App\Ai\Support\SectionCssSubmission;
use App\Services\SiteBuilder\SiteSectionCss;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Validates scoped section CSS and stores the accepted patch for the editor job.
 */
class SubmitSectionCssPatch implements Tool
{
    public function __construct(
        protected string $target,
        protected SectionCssSubmission $submission,
    ) {}

    public function description(): Stringable|string
    {
        return 'Submit the final scoped CSS patch for this section. If validation fails, fix selectors and try again.';
    }

    public function handle(Request $request): Stringable|string
    {
        $css = SiteSectionCss::sanitize((string) $request['css']);
        $errors = SiteSectionCss::validate($this->target, $css);

        if ($errors !== []) {
            return 'Rejected: '.implode(' ', $errors);
        }

        $this->submission->payload = [
            'css' => $css,
            'summary' => trim((string) $request['summary']),
        ];

        return 'Accepted. Do not call this tool again.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'css' => $schema->string()->required(),
            'summary' => $schema->string()->required()->description('One short sentence for the site owner'),
        ];
    }
}

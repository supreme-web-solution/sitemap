<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use App\Services\SiteBuilder\SiteEditOpCatalog;
use App\Services\SiteBuilder\SiteTemplateContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DescribeSiteTemplate implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'Read the active Blade theme, catalog design, accent, and section layout presets. Call first on visual edits.';
    }

    public function handle(Request $request): Stringable|string
    {
        return implode("\n\n", [
            SiteTemplateContext::forSite($this->site),
            "Editable paths:\n".SiteEditOpCatalog::plannerReference(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}

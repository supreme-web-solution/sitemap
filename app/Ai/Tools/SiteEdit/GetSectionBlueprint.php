<?php

namespace App\Ai\Tools\SiteEdit;

use App\Ai\Tools\SiteEdit\Concerns\UsesSiteEditExecutorState;
use App\Services\SiteBuilder\CustomHomeSection;
use App\Services\SiteBuilder\SiteSectionCss;
use App\Services\SiteBuilder\SiteSectionDesignBlueprint;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetSectionBlueprint implements Tool
{
    use UsesSiteEditExecutorState;

    public function description(): Stringable|string
    {
        return 'Return HTML/CSS structure hints and allowed scoped CSS selectors for a section key (hero, services, footer, custom, …).';
    }

    public function handle(Request $request): Stringable|string
    {
        $target = trim((string) ($request['section'] ?? ''));

        if ($target === 'custom') {
            return SiteSectionDesignBlueprint::forTarget('custom');
        }

        if (CustomHomeSection::isKey($target)) {
            return SiteSectionDesignBlueprint::forTarget('custom')
                ."\nBlock id: ".CustomHomeSection::idFromKey($target);
        }

        if (! isset(SiteSectionCss::TARGETS[$target])) {
            return 'Unknown section. Use header, hero, footer, or a homepage block key: '
                .implode(', ', SiteSectionCss::keys()).', custom.';
        }

        $definition = SiteSectionCss::TARGETS[$target];

        return implode("\n", [
            SiteSectionDesignBlueprint::forTarget($target),
            'Allowed CSS selectors: '.implode(', ', $definition['scope']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section' => $schema->string()->required(),
        ];
    }
}

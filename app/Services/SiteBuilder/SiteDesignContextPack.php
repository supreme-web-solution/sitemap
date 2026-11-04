<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;

/**
 * Small context bundle for each design turn (manifest + hot section), not the whole site.
 */
class SiteDesignContextPack
{
    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $previewEdits
     */
    public static function userPrompt(
        Site $site,
        string $target,
        string $userInstruction,
        ?string $currentCss,
        array $previewEdits = [],
    ): string {
        $site->loadMissing('lead');
        $definition = SiteSectionCss::TARGETS[$target];
        $instruction = $previewEdits !== []
            ? PreviewEditPrompt::format($previewEdits, $userInstruction)
            : $userInstruction;

        $conversation = app(SiteEditConversation::class)->transcript($site);

        return implode("\n\n", array_filter([
            $conversation !== '' ? $conversation : null,
            self::manifest($site),
            'Hot section: '.$definition['label'].' ('.$target.')',
            'Template: '.$site->template->value,
            'Accent (--accent): '.($site->content['accent'] ?? '#2563eb'),
            'Business: '.$site->lead->name,
            'Allowed selectors: '.implode(', ', $definition['scope']),
            'Markup reference:',
            SiteSectionDesignBlueprint::forTarget($target),
            $currentCss !== null && $currentCss !== ''
                ? "Current CSS patch for this section:\n".$currentCss
                : 'Current CSS patch: (none yet)',
            'Task: '.$instruction,
            'Output a full replacement CSS patch for this section only. Use flexbox/grid for alignment (logo left, center, or right). Modern spacing, balanced columns where relevant.',
        ]));
    }

    public static function manifest(Site $site): string
    {
        $variants = SectionVariants::clean($site->content['variants'] ?? null);
        $css = SiteSectionCss::clean($site->content['section_css'] ?? null);
        $lines = ['Site manifest (paths only):'];

        foreach (SiteSectionCss::keys() as $key) {
            $flags = [];

            if (isset($css[$key])) {
                $flags[] = 'custom-css';
            }

            if (isset($variants[$key]) && $variants[$key] !== (SectionVariants::CATALOG[$key]['default'] ?? null)) {
                $flags[] = 'layout='.$variants[$key];
            }

            $lines[] = '- '.$key.($flags !== [] ? ' ['.implode(', ', $flags).']' : '');
        }

        return implode("\n", $lines);
    }
}

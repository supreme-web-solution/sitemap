<?php

namespace App\Services\SiteBuilder;

/**
 * Turn structured preview clicks into instructions for the design executor.
 */
class PreviewEditPrompt
{
    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $edits
     */
    public static function format(array $edits, string $userInstruction): string
    {
        if ($edits === []) {
            return $userInstruction;
        }

        $lines = [
            '[Preview targets — persist changes in scoped CSS for these regions; match by selector and visible text.]',
        ];

        foreach (array_values($edits) as $index => $edit) {
            $region = $edit['region'] ?? '';
            $label = SiteSectionCss::TARGETS[$region]['label'] ?? $region;
            $parts = [
                ($index + 1).'.',
                '<'.$edit['tag'].'>',
                'region='.$region.' ('.$label.')',
                'selector='.$edit['selector'],
            ];

            if (filled($edit['text'] ?? null)) {
                $parts[] = 'text="'.str_replace('"', "'", (string) $edit['text']).'"';
            }

            if (filled($edit['href'] ?? null)) {
                $parts[] = 'href='.(string) $edit['href'];
            }

            if (filled($edit['action'] ?? null)) {
                $parts[] = 'action='.(string) $edit['action'];
            }

            $lines[] = implode(' ', $parts);
        }

        $lines[] = '';
        $lines[] = 'User request: '.trim($userInstruction);

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{region: string, selector: string, tag: string, text?: string|null, href?: string|null, action?: string|null}>  $edits
     * @return list<string>
     */
    public static function regions(array $edits): array
    {
        $regions = [];

        foreach ($edits as $edit) {
            $region = $edit['region'] ?? '';

            if ($region !== '' && isset(SiteSectionCss::TARGETS[$region])) {
                $regions[] = $region;
            }
        }

        return array_values(array_unique($regions));
    }
}

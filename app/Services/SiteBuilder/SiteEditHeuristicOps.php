<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Rule-based document ops when the LLM planner is unavailable or as a supplement.
 */
class SiteEditHeuristicOps
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function build(string $instruction, Site $site, ?string $designTarget = null): array
    {
        $ops = [];
        $body = trim($instruction);

        foreach (['headline' => SiteContentSchema::HEADLINE_MAX, 'tagline' => 200, 'cta_label' => 40] as $field => $max) {
            if (preg_match('/'.$field.'(?:\s+to|\s*:)\s*[\'\"](.+?)[\'\"]/i', $body, $match) === 1) {
                $ops[] = ['op' => 'set', 'path' => $field, 'value' => Str::limit(trim($match[1]), $max, '')];
            }
        }

        if (preg_match('/button(?:\s+to|\s*:)\s*[\'\"](.+?)[\'\"]/i', $body, $match) === 1) {
            $ops[] = ['op' => 'set', 'path' => 'cta_label', 'value' => Str::limit(trim($match[1]), 40, '')];
        }

        if (preg_match('/\baccent\b.*?(\#[0-9a-fA-F]{6})/i', $body, $match) === 1) {
            $ops[] = ['op' => 'set', 'path' => 'accent', 'value' => Str::lower($match[1])];
        }

        $sections = SiteChatInstruction::mentionedSections($body);

        if ($designTarget !== null && ! in_array($designTarget, $sections, true)) {
            $sections[] = $designTarget;
        }

        foreach ($sections as $section) {
            if ($section === 'hero' && SiteSectionCssIntentChecker::instructionWantsHorizontalCenter($body)) {
                $ops[] = [
                    'op' => 'set',
                    'path' => 'section_css.hero',
                    'value' => SiteSectionCssPresets::heroCenteredOverlay(),
                ];
            }

            if ($section === 'hero' && (
                SiteSectionCssIntentChecker::instructionWantsHeroPhotoBackground($body)
                || SiteSectionCssIntentChecker::instructionWantsFullWidthHero($body)
            )) {
                $ops[] = [
                    'op' => 'set',
                    'path' => 'variants.hero',
                    'value' => 'overlay',
                ];
                $ops[] = [
                    'op' => 'set',
                    'path' => 'section_css.hero',
                    'value' => SiteSectionCssPresets::heroPhotoBackground(),
                ];
            }

            $variant = self::explicitVariant($body, $section);

            if ($variant !== null) {
                $ops[] = ['op' => 'set', 'path' => 'variants.'.$section, 'value' => $variant];
            }
        }

        return SiteEditOpCatalog::sanitize($ops);
    }

    protected static function explicitVariant(string $instruction, string $section): ?string
    {
        if (! isset(SectionVariants::CATALOG[$section])) {
            return null;
        }

        $lower = Str::lower($instruction);

        foreach (SectionVariants::CATALOG[$section]['variants'] as $id => $label) {
            if (Str::contains($lower, $id) || Str::contains($lower, Str::lower($label))) {
                return $id;
            }
        }

        return null;
    }
}

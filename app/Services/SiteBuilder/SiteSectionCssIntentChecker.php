<?php

namespace App\Services\SiteBuilder;

/**
 * Deterministic checks that layout language in the request matches scoped CSS for any section.
 */
class SiteSectionCssIntentChecker
{
    /**
     * @return list<string>
     */
    public static function issues(string $userInstruction, string $css, string $target): array
    {
        if (! isset(SiteSectionCss::TARGETS[$target])) {
            return [];
        }

        $definition = SiteSectionCss::TARGETS[$target];
        $label = $definition['label'];
        $scopeHint = implode(', ', $definition['scope']);
        $normalizedCss = strtolower(preg_replace('/\s+/', '', $css) ?? '');

        $issues = [];

        if (self::instructionWantsHorizontalCenter($userInstruction) && ! self::cssHasHorizontalCenter($normalizedCss)) {
            $issues[] = sprintf(
                'User asked to center %s content but the CSS patch does not set text-align: center or align-items: center (allowed selectors: %s).',
                $label,
                $scopeHint,
            );
        }

        if (self::instructionWantsHorizontalLeft($userInstruction) && ! self::cssHasHorizontalLeft($normalizedCss)) {
            $issues[] = sprintf(
                'User asked for left-aligned %s content but the CSS patch does not set text-align: left or align-items: flex-start.',
                $label,
            );
        }

        if (self::instructionWantsHorizontalRight($userInstruction) && ! self::cssHasHorizontalRight($normalizedCss)) {
            $issues[] = sprintf(
                'User asked for right-aligned %s content but the CSS patch does not set text-align: right, justify-content: flex-end, or margin-left: auto on branding blocks.',
                $label,
            );
        }

        if (self::instructionWantsFlexAlignment($userInstruction) && ! self::cssHasFlexAlignment($normalizedCss)) {
            $issues[] = sprintf(
                'User asked to reposition or balance %s layout but the CSS patch has no flex/grid alignment (justify-content, align-items, or text-align).',
                $label,
            );
        }

        return $issues;
    }

    public static function instructionWantsHorizontalCenter(string $instruction): bool
    {
        if (preg_match('/\b(centre|center|centralize|centralise|centraliz\w*|centred|centered|middle)\b/i', $instruction) !== 1) {
            return false;
        }

        return preg_match('/\b(left|right)\b/i', $instruction) !== 1;
    }

    public static function instructionWantsHorizontalLeft(string $instruction): bool
    {
        return preg_match('/\b(left[\s-]?align|align[\s-]?left|move.*left|logo.*left|brand.*left)\b/i', $instruction) === 1;
    }

    public static function instructionWantsHorizontalRight(string $instruction): bool
    {
        return preg_match('/\b(right[\s-]?align|align[\s-]?right|move.*right|logo.*right|brand.*right)\b/i', $instruction) === 1;
    }

    public static function instructionWantsHeroPhotoBackground(string $instruction): bool
    {
        return SiteEditIntent::wantsHeroPhotoBackground($instruction);
    }

    public static function instructionWantsFullWidthHero(string $instruction): bool
    {
        return SiteEditIntent::wantsFullWidthHero($instruction);
    }

    public static function instructionWantsFlexAlignment(string $instruction): bool
    {
        if (self::instructionWantsHorizontalCenter($instruction)
            || self::instructionWantsHorizontalLeft($instruction)
            || self::instructionWantsHorizontalRight($instruction)) {
            return false;
        }

        return preg_match('/\b(columns?|balance|spread|space between|reposition|move the logo|move logo|footer layout|nav layout|stack|grid)\b/i', $instruction) === 1;
    }

    public static function cssHasHorizontalCenter(string $normalizedCss): bool
    {
        return str_contains($normalizedCss, 'text-align:center')
            || str_contains($normalizedCss, 'align-items:center')
            || str_contains($normalizedCss, 'justify-content:center')
            || str_contains($normalizedCss, 'margin-left:auto;margin-right:auto');
    }

    public static function cssHasHorizontalLeft(string $normalizedCss): bool
    {
        return str_contains($normalizedCss, 'text-align:left')
            || str_contains($normalizedCss, 'align-items:flex-start')
            || str_contains($normalizedCss, 'justify-content:flex-start');
    }

    public static function cssHasHorizontalRight(string $normalizedCss): bool
    {
        return str_contains($normalizedCss, 'text-align:right')
            || str_contains($normalizedCss, 'justify-content:flex-end')
            || str_contains($normalizedCss, 'align-items:flex-end')
            || str_contains($normalizedCss, 'margin-left:auto');
    }

    public static function cssHasFlexAlignment(string $normalizedCss): bool
    {
        return str_contains($normalizedCss, 'justify-content')
            || str_contains($normalizedCss, 'align-items')
            || str_contains($normalizedCss, 'text-align')
            || str_contains($normalizedCss, 'grid-template-columns');
    }
}

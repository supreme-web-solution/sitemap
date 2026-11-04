<?php

namespace App\Services\SiteBuilder;

use App\Enums\SiteSection;
use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Turn plain-English chat into instructions the site editor understands.
 */
class SiteChatInstruction
{
    /**
     * Words that suggest the user wants a layout change, not only copy tweaks.
     *
     * @var list<string>
     */
    protected const LAYOUT_HINTS = [
        'redesign',
        're-design',
        're design',
        'layout',
        'unbalanced',
        'not balanced',
        'imbalanced',
        'look terrible',
        'looks terrible',
        'look bad',
        'looks bad',
        'looks ugly',
        'look ugly',
        'fix the',
        'make it look',
        'more balanced',
        'better layout',
    ];

    /**
     * Phrases that ask for a nicer visual without saying "redesign".
     *
     * @var list<string>
     */
    protected const DESIGN_HINTS = [
        'nice design',
        'modern design',
        'professional',
        'make it modern',
        'look modern',
        'more modern',
        'premium look',
        'polished',
        'give me a nice',
        'better design',
        'improve the look',
        'fix the look',
        'make the footer',
        'make the hero',
        'cleaner look',
        'agency style',
        'look professional',
    ];

    /**
     * Extra phrases that map to a section key when mentioned in chat.
     *
     * @var array<string, list<string>>
     */
    protected const SECTION_ALIASES = [
        'hero' => ['hero', 'banner', 'above the fold', 'top section'],
        'footer' => ['footer', 'bottom of the page', 'page footer'],
        'services' => ['services section', 'service section', 'what we offer'],
        'contact' => ['contact section', 'contact form'],
        'gallery' => ['gallery', 'photos section', 'photo gallery'],
        'reviews' => [
            'review section',
            'reviews section',
            'testimonial',
            'testimonials',
            'testimonial section',
            'testimonials section',
            'google reviews',
            'customer reviews',
        ],
        'faq' => ['faq section', 'questions section'],
        'hours' => ['hours section', 'opening hours section', 'visit section', '#visit'],
        'process' => ['how it works', 'process section', 'steps section'],
        'why' => ['why choose us', 'why us section', 'why trust us'],
        'about_page' => ['about page', 'about story'],
        'services_page' => ['services page', 'service listing', 'our services page'],
        'mission' => ['mission', 'our mission'],
        'areas' => ['areas we serve', 'service area section'],
        'header' => ['header', 'navigation', 'nav bar', 'top bar', 'menu bar'],
    ];

    /**
     * Expand a user message into a clearer instruction for the copy planner.
     */
    public static function expand(string $body): string
    {
        $body = trim($body);

        if (SiteStructuralIntent::wantsAddSection($body)) {
            $keys = self::mentionedSections($body);
            $labels = array_map(
                fn (string $key) => SiteSection::tryFrom($key)?->label() ?? $key,
                $keys,
            );

            return __('Add these homepage sections to the sections array (keep them in a sensible order): :sections. Keep all existing copy and facts unless the user asked to rewrite them. Original request: :request', [
                'sections' => implode(', ', $labels),
                'request' => $body,
            ]);
        }

        if (SiteStructuralIntent::wantsRemoveSection($body)) {
            $keys = self::mentionedSections($body);
            $labels = array_map(
                fn (string $key) => SiteSection::tryFrom($key)?->label() ?? $key,
                $keys,
            );

            return __('Remove these homepage sections from the sections array: :sections. Keep all other copy and facts. Original request: :request', [
                'sections' => implode(', ', $labels),
                'request' => $body,
            ]);
        }

        return $body;
    }

    /**
     * Explicit preset / variant swap (inspector "Redesign layout"), not free-form design code.
     */
    public static function wantsLayoutPreset(string $body): bool
    {
        $lower = Str::lower($body);

        return Str::contains($lower, [
            'layout variant',
            'pick a different layout variant',
            'set variants.',
            'variants.',
        ]);
    }

    /**
     * @return list<string>
     */
    public static function designTargets(string $body, ?string $forced = null, ?Site $site = null, ?SiteRenderer $renderer = null): array
    {
        if ($forced !== null && isset(SiteSectionCss::TARGETS[$forced])) {
            return [$forced];
        }

        if (self::wantsWholeSiteDesign($body) && $site !== null && $renderer !== null) {
            return $renderer->designTargetKeys($site);
        }

        $mentioned = self::mentionedSections($body);

        if ($mentioned !== []) {
            return $mentioned;
        }

        if ($site !== null && $renderer !== null && self::wantsSitewideVisualRefresh($body)) {
            return $renderer->designTargetKeys($site);
        }

        return [];
    }

    /**
     * User asked to restyle the full site (header through footer).
     */
    public static function wantsWholeSiteDesign(string $body): bool
    {
        $lower = Str::lower($body);

        foreach ([
            'whole site',
            'entire site',
            'full site',
            'all sections',
            'every section',
            'site wide',
            'sitewide',
            'across the site',
            'whole website',
            'entire website',
            'full website',
        ] as $phrase) {
            if (Str::contains($lower, $phrase)) {
                return true;
            }
        }

        return preg_match('/\b(entire|whole|full)\s+(site|website|page)\b/i', $body) === 1;
    }

    /**
     * Whether the message is probably asking for a section layout change.
     */
    public static function wantsLayoutChange(string $body): bool
    {
        $lower = Str::lower($body);

        foreach (self::LAYOUT_HINTS as $hint) {
            if (Str::contains($lower, $hint)) {
                return true;
            }
        }

        return preg_match('/\bredesign\b/i', $body) === 1
            || preg_match('/\bredes[ai]gn\b/i', $body) === 1;
    }

    /**
     * Layout change or a nicer visual for a named section (footer, hero, etc.).
     */
    public static function wantsSectionVisualChange(string $body): bool
    {
        if (self::wantsWholeSiteDesign($body)) {
            return self::wantsLayoutChange($body) || self::wantsSitewideVisualRefresh($body);
        }

        if (self::mentionedSections($body) === []) {
            return false;
        }

        if (self::wantsLayoutChange($body)) {
            return true;
        }

        $lower = Str::lower($body);

        foreach (self::DESIGN_HINTS as $hint) {
            if (Str::contains($lower, $hint)) {
                return true;
            }
        }

        return preg_match('/\b(nice|modern|professional|prettier|cleaner)\b/i', $body) === 1
            && preg_match('/\b(design|look|style|footer|hero|header|section|site|website)\b/i', $body) === 1;
    }

    /**
     * Visual refresh language without naming a single section (applies to all visible regions).
     */
    protected static function wantsSitewideVisualRefresh(string $body): bool
    {
        if (self::wantsLayoutChange($body)) {
            return true;
        }

        $lower = Str::lower($body);

        foreach (self::DESIGN_HINTS as $hint) {
            if (Str::contains($lower, $hint)) {
                return true;
            }
        }

        return preg_match('/\b(nice|modern|professional|prettier|cleaner|polished)\b/i', $body) === 1
            && preg_match('/\b(design|look|style|site|website)\b/i', $body) === 1;
    }

    /**
     * Section keys mentioned in the message, most specific first.
     *
     * @return list<string>
     */
    public static function mentionedSections(string $body): array
    {
        $lower = Str::lower($body);
        $found = [];

        foreach (SiteSectionCss::TARGETS as $key => $definition) {
            if (self::mentionsSection($lower, $key, $definition['label'])) {
                $found[] = $key;
            }
        }

        foreach (SiteSection::cases() as $section) {
            $key = $section->value;

            if (in_array($key, $found, true)) {
                continue;
            }

            if (self::mentionsSection($lower, $key, $section->label())) {
                $found[] = $key;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @param  array{label: string, default: string, variants: array<string, string>}  $definition
     */
    protected static function mentionsSection(string $lowerBody, string $key, string $label): bool
    {
        if (Str::contains($lowerBody, $key)) {
            return true;
        }

        if (Str::contains($lowerBody, Str::lower($label))) {
            return true;
        }

        foreach (self::SECTION_ALIASES[$key] ?? [] as $alias) {
            if (Str::contains($lowerBody, $alias)) {
                return true;
            }
        }

        return false;
    }
}

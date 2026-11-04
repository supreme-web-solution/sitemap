<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Str;

/**
 * Layout variants for individual home page sections.
 *
 * A website is drawn by one of three Blade themes (colours, fonts, tone). On top of
 * the theme, a design template picks one variant per section, so two sites on the
 * same theme can still have a different hero, services layout, gallery and so on.
 *
 * After a template is chosen, the agency (or AI) can change one section's variant
 * without swapping the whole template. Those overrides live on the site content
 * under `variants` and win over the catalog row.
 *
 * Variants are applied as classes on the body (for example `hero-v-overlay`) and
 * styled in `sites/partials/section-variants.blade.php`.
 */
class SectionVariants
{
    /**
     * Every section that can change layout, with its default and available variants.
     *
     * @var array<string, array{label: string, default: string, variants: array<string, string>}>
     */
    public const CATALOG = [
        'hero' => [
            'label' => 'Hero',
            'default' => 'classic',
            'variants' => [
                'classic' => 'Classic (theme default)',
                'centered' => 'Centered headline',
                'overlay' => 'Full photo with overlay',
                'split' => 'Text and photo side by side',
                'minimal' => 'Minimal, big type',
                'photo-top' => 'Photo banner above the text',
                'card' => 'Text card over the photo',
            ],
        ],
        'services' => [
            'label' => 'Services',
            'default' => 'cards',
            'variants' => [
                'cards' => 'Cards',
                'list' => 'Rows',
                'numbered' => 'Numbered practice areas',
                'columns' => 'Open columns',
                'featured' => 'One featured, rest smaller',
                'tiles' => 'Soft colour tiles',
                'outline' => 'Outlined',
            ],
        ],
        'about' => [
            'label' => 'About',
            'default' => 'split',
            'variants' => [
                'split' => 'Title left, text right',
                'centered' => 'Centered and narrow',
                'quote' => 'Large pull quote',
                'card' => 'Text on a raised card',
                'columns' => 'Wide text in two columns',
                'highlight' => 'Brand colour band',
            ],
        ],
        'process' => [
            'label' => 'How it works',
            'default' => 'steps',
            'variants' => [
                'steps' => 'Numbered steps in a row',
                'timeline' => 'Vertical timeline',
                'stacked' => 'Stacked with large numbers',
                'cards' => 'Step cards',
                'split' => 'Intro left, steps right',
            ],
        ],
        'why' => [
            'label' => 'Why choose us',
            'default' => 'cards',
            'variants' => [
                'cards' => 'Reason cards',
                'numbered' => 'Numbered reasons',
                'list' => 'Check list',
                'band' => 'Brand colour band',
                'split' => 'Intro left, reasons right',
            ],
        ],
        'gallery' => [
            'label' => 'Gallery',
            'default' => 'grid',
            'variants' => [
                'grid' => 'Even grid',
                'masonry' => 'Masonry',
                'mosaic' => 'Mosaic with a big first photo',
                'strip' => 'Swipeable strip',
                'polaroid' => 'Polaroid frames',
                'duo' => 'Two large photos per row',
            ],
        ],
        'areas' => [
            'label' => 'Areas we serve',
            'default' => 'chips',
            'variants' => [
                'chips' => 'City chips',
                'grid' => 'City cards',
                'list' => 'Plain list',
                'split' => 'Intro left, cities right',
            ],
        ],
        'faq' => [
            'label' => 'FAQ',
            'default' => 'accordion',
            'variants' => [
                'accordion' => 'Accordion',
                'two-column' => 'Two columns',
                'lined' => 'Lined list',
                'cards' => 'Tinted cards',
                'numbered' => 'Numbered questions',
            ],
        ],
        'hours' => [
            'label' => 'Hours & map',
            'default' => 'split',
            'variants' => [
                'split' => 'Hours left, map right',
                'stacked' => 'Stacked',
                'cards' => 'Raised cards',
            ],
        ],
        'contact' => [
            'label' => 'Contact',
            'default' => 'split',
            'variants' => [
                'split' => 'Intro left, details right',
                'stacked' => 'Centered stack',
                'cards' => 'Detail cards',
            ],
        ],
        'header' => [
            'label' => 'Navigation',
            'default' => 'line',
            'variants' => [
                'line' => 'Accent underline',
                'grow' => 'Underline grows on hover',
                'slide' => 'Underline slides in',
                'bar' => 'Thick brand bar',
                'dot' => 'Dot under the link',
                'pill' => 'Pill highlight',
            ],
        ],
        'footer' => [
            'label' => 'Footer',
            'default' => 'columns',
            'variants' => [
                'columns' => 'Brand, links, services, hours',
                'mega' => 'Wide four-column',
                'stacked' => 'Stacked',
                'compact' => 'Single row',
                'cta' => 'Call bar on top',
            ],
        ],
        'about_page' => [
            'label' => 'About page',
            'default' => 'story',
            'variants' => [
                'story' => 'Text left, portrait right',
                'portrait' => 'Portrait left, text right',
                'stacked' => 'Photo above the story',
                'band' => 'Brand colour band',
            ],
        ],
        'mission' => [
            'label' => 'Our mission',
            'default' => 'split',
            'variants' => [
                'split' => 'Title left, text right',
                'centered' => 'Centered',
                'band' => 'Brand colour band',
            ],
        ],
        'services_page' => [
            'label' => 'Services page',
            'default' => 'zigzag',
            'variants' => [
                'zigzag' => 'Alternating image and summary',
                'reverse' => 'Start with the image on the left',
                'stack' => 'Summary left, image right',
                'featured' => 'First service large, then alternate',
                'cards' => 'Compact cards',
            ],
        ],
    ];

    /**
     * Inner-page layouts that follow the homepage mix, so each template's About
     * and Services pages look different without asking AI to fill them.
     *
     * @param  array<string, mixed>  $homeVariants
     * @return array<string, string>
     */
    public static function complementary(array $homeVariants): array
    {
        $about = is_string($homeVariants['about'] ?? null) ? $homeVariants['about'] : 'split';
        $services = is_string($homeVariants['services'] ?? null) ? $homeVariants['services'] : 'cards';
        $hero = is_string($homeVariants['hero'] ?? null) ? $homeVariants['hero'] : 'classic';

        return [
            'header' => match ($hero) {
                'overlay', 'card' => 'bar',
                'centered' => 'pill',
                'photo-top' => 'slide',
                'split' => 'grow',
                'minimal' => 'dot',
                default => 'line',
            },
            'about_page' => match ($about) {
                'centered', 'quote' => 'stacked',
                'columns' => 'portrait',
                'highlight' => 'band',
                default => 'story',
            },
            'services_page' => match ($services) {
                'list', 'numbered' => 'stack',
                'featured' => 'featured',
                'tiles', 'outline' => 'cards',
                default => 'zigzag',
            },
            'mission' => match ($about) {
                'centered', 'quote' => 'centered',
                'highlight' => 'band',
                default => 'split',
            },
        ];
    }

    /**
     * The default variant for every section.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return array_map(fn (array $definition) => $definition['default'], self::CATALOG);
    }

    /**
     * Fill missing keys with defaults, then drop unknown values.
     *
     * @param  array<string, mixed>|null  $variants
     * @return array<string, string>
     */
    public static function withDefaults(?array $variants): array
    {
        return array_merge(self::defaults(), self::clean($variants));
    }

    /**
     * Site overrides win over the catalog row.
     *
     * @param  array<string, mixed>|null  $override
     * @param  array<string, mixed>|null  $fallback
     * @return array<string, string>
     */
    public static function merge(?array $override, ?array $fallback): array
    {
        return self::withDefaults(array_merge(
            is_array($fallback) ? $fallback : [],
            is_array($override) ? $override : [],
        ));
    }

    /**
     * Keep only known sections with a known variant.
     *
     * @param  array<string, mixed>|null  $variants
     * @return array<string, string>
     */
    public static function clean(?array $variants): array
    {
        $clean = [];

        foreach (self::CATALOG as $section => $definition) {
            $value = $variants[$section] ?? null;

            if (is_string($value) && array_key_exists($value, $definition['variants'])) {
                $clean[$section] = $value;
            }
        }

        return $clean;
    }

    /**
     * Body classes for the chosen variants. Defaults add no class.
     *
     * @param  array<string, mixed>|null  $variants
     */
    public static function bodyClasses(?array $variants): string
    {
        $classes = [];

        foreach (self::clean($variants) as $section => $variant) {
            if ($variant !== self::CATALOG[$section]['default']) {
                $classes[] = $section.'-v-'.$variant;
            }
        }

        return implode(' ', $classes);
    }

    /**
     * A readable one-line summary, for example "Overlay hero, list services".
     *
     * @param  array<string, mixed>|null  $variants
     */
    public static function summary(?array $variants): string
    {
        $parts = [];

        foreach (self::clean($variants) as $section => $variant) {
            if ($variant === self::CATALOG[$section]['default']) {
                continue;
            }

            $parts[] = str_replace('-', ' ', $variant).' '.strtolower(self::CATALOG[$section]['label']);
        }

        return implode(', ', $parts);
    }

    /**
     * Option lists for the editor UI.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::CATALOG as $section => $definition) {
            $choices[$section] = [];

            foreach ($definition['variants'] as $value => $label) {
                $choices[$section][] = ['value' => $value, 'label' => $label];
            }
        }

        return $choices;
    }

    /**
     * Cycle to the next layout for a section.
     */
    public static function next(string $section, ?string $current): string
    {
        return self::nextInList($section, $current, array_keys(self::CATALOG[$section]['variants'] ?? []));
    }

    /**
     * Pick a layout from chat: intent-aware for modern/professional asks, otherwise cycle pro layouts only.
     */
    public static function pickForChat(string $section, string $instruction, ?string $current): string
    {
        $current = self::clean([$section => $current])[$section] ?? self::CATALOG[$section]['default'];
        $intent = self::intentVariant($section, $instruction, $current);

        if ($intent !== null) {
            return $intent;
        }

        return self::nextInList($section, $current, self::chatCycle($section));
    }

    /**
     * Layout keys used when the user asks to redesign from chat (skips awkward presets).
     *
     * @return list<string>
     */
    public static function chatCycle(string $section): array
    {
        if ($section === 'footer') {
            return ['columns', 'mega', 'cta'];
        }

        return array_keys(self::CATALOG[$section]['variants'] ?? []);
    }

    /**
     * @param  list<string>  $allowed
     */
    protected static function nextInList(string $section, ?string $current, array $allowed): string
    {
        if ($allowed === []) {
            return self::CATALOG[$section]['default'] ?? 'classic';
        }

        $index = array_search($current, $allowed, true);

        return $allowed[(($index === false ? -1 : $index) + 1) % count($allowed)];
    }

    /**
     * Map natural language to a concrete variant when the user names a style.
     */
    protected static function intentVariant(string $section, string $instruction, string $current): ?string
    {
        $lower = Str::lower($instruction);

        if ($section !== 'footer') {
            return null;
        }

        if (Str::contains($lower, ['stacked', 'single column'])) {
            return 'stacked';
        }

        if (Str::contains($lower, ['minimal footer', 'compact footer', 'simple footer'])) {
            return 'compact';
        }

        if (Str::contains($lower, ['cta bar', 'call bar on top'])) {
            return 'cta';
        }

        $wantsQuality = Str::contains($lower, [
            'modern', 'professional', 'nice design', 'premium', 'polished', 'agency', 'give me a nice',
        ]);

        if ($wantsQuality) {
            if (in_array($current, ['stacked', 'compact', 'cta'], true)) {
                return 'columns';
            }

            return $current === 'mega' ? 'mega' : 'columns';
        }

        $wantsBalance = Str::contains($lower, [
            'balanced', 'unbalanced', 'not balanced', 'terrible', 'look bad', 'looks bad',
        ]);

        if ($wantsBalance) {
            if (in_array($current, ['stacked', 'compact'], true)) {
                return 'columns';
            }

            return $current === 'columns' ? 'mega' : 'columns';
        }

        return null;
    }

    /**
     * A plain list of variant keys, for prompts.
     */
    public static function promptList(string $section): string
    {
        $keys = array_keys(self::CATALOG[$section]['variants'] ?? []);

        return implode(', ', $keys);
    }
}

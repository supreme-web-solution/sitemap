<?php

namespace App\Services\SiteBuilder;

use App\Enums\SiteSection;

/**
 * Agency-chosen design tokens layered on top of a template: fonts, corner style, spacing
 * and a background style per home page section.
 *
 * Every token is optional. A missing token means "use the template's own look".
 */
class SiteDesign
{
    public const KEYS = ['font', 'radius', 'spacing'];

    /**
     * @var array<string, array{label: string, heading: string, body: string, google: string|null}>
     */
    public const FONTS = [
        'inter' => ['label' => 'Inter (clean)', 'heading' => "'Inter', system-ui, sans-serif", 'body' => "'Inter', system-ui, sans-serif", 'google' => 'Inter:wght@400;500;600;700;800'],
        'poppins' => ['label' => 'Poppins (friendly)', 'heading' => "'Poppins', system-ui, sans-serif", 'body' => "'Poppins', system-ui, sans-serif", 'google' => 'Poppins:wght@400;500;600;700'],
        'playfair' => ['label' => 'Playfair Display (elegant)', 'heading' => "'Playfair Display', Georgia, serif", 'body' => "'Source Sans 3', system-ui, sans-serif", 'google' => 'Playfair+Display:wght@500;600;700&family=Source+Sans+3:wght@400;600'],
        'merriweather' => ['label' => 'Merriweather (classic)', 'heading' => "'Merriweather', Georgia, serif", 'body' => "'Open Sans', system-ui, sans-serif", 'google' => 'Merriweather:wght@400;700&family=Open+Sans:wght@400;600'],
        'space-grotesk' => ['label' => 'Space Grotesk (modern)', 'heading' => "'Space Grotesk', system-ui, sans-serif", 'body' => "'DM Sans', system-ui, sans-serif", 'google' => 'Space+Grotesk:wght@500;700&family=DM+Sans:wght@400;500;700'],
        'system' => ['label' => 'System fonts (fastest)', 'heading' => 'system-ui, -apple-system, Segoe UI, sans-serif', 'body' => 'system-ui, -apple-system, Segoe UI, sans-serif', 'google' => null],
    ];

    /**
     * @var array<string, array{label: string, value: string}>
     */
    public const RADII = [
        'sharp' => ['label' => 'Sharp', 'value' => '2px'],
        'soft' => ['label' => 'Soft', 'value' => '10px'],
        'round' => ['label' => 'Round', 'value' => '24px'],
    ];

    /**
     * @var array<string, array{label: string, value: string}>
     */
    public const SPACINGS = [
        'compact' => ['label' => 'Compact', 'value' => '3rem'],
        'spacious' => ['label' => 'Spacious', 'value' => '7.5rem'],
    ];

    /**
     * @var list<string>
     */
    public const SECTION_STYLES = ['tint', 'accent', 'dark'];

    /**
     * Allowed values for each design token.
     *
     * @return array<string, list<string>>
     */
    public static function options(): array
    {
        return [
            'font' => array_keys(self::FONTS),
            'radius' => array_keys(self::RADII),
            'spacing' => array_keys(self::SPACINGS),
        ];
    }

    /**
     * Option lists for the editor UI.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function choices(): array
    {
        return [
            'font' => collect(self::FONTS)->map(fn (array $font, string $key) => ['value' => $key, 'label' => $font['label']])->values()->all(),
            'radius' => collect(self::RADII)->map(fn (array $radius, string $key) => ['value' => $key, 'label' => $radius['label']])->values()->all(),
            'spacing' => collect(self::SPACINGS)->map(fn (array $spacing, string $key) => ['value' => $key, 'label' => $spacing['label']])->values()->all(),
            'sectionStyle' => [
                ['value' => 'tint', 'label' => 'Soft tint'],
                ['value' => 'accent', 'label' => 'Brand colour'],
                ['value' => 'dark', 'label' => 'Dark'],
            ],
        ];
    }

    /**
     * Keep only valid design tokens.
     *
     * @param  array<string, mixed>|null  $design
     * @return array<string, string>
     */
    public static function clean(?array $design): array
    {
        $clean = [];

        foreach (self::options() as $key => $allowed) {
            $value = $design[$key] ?? null;

            if (is_string($value) && in_array($value, $allowed, true)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Keep only valid per-section background styles for known sections.
     *
     * @param  array<string, mixed>|null  $styles
     * @return array<string, string>
     */
    public static function cleanSectionStyles(?array $styles): array
    {
        $clean = [];

        foreach (SiteSection::cases() as $section) {
            $value = $styles[$section->value] ?? null;

            if (is_string($value) && in_array($value, self::SECTION_STYLES, true)) {
                $clean[$section->value] = $value;
            }
        }

        return $clean;
    }

    /**
     * Build the CSS that applies the chosen tokens. Empty when nothing is customised.
     *
     * @param  array<string, mixed>  $content
     */
    public static function css(array $content): string
    {
        $design = self::clean($content['design'] ?? null);
        $rules = [];

        if (isset($design['font'])) {
            $font = self::FONTS[$design['font']];
            $rules[] = "body { font-family: {$font['body']}; }";
            $rules[] = "h1, h2, h3, .brand, .section-title { font-family: {$font['heading']}; }";
        }

        if (isset($design['radius'])) {
            $rules[] = ':root { --radius: '.self::RADII[$design['radius']]['value'].'; }';
        }

        if (isset($design['spacing'])) {
            $rules[] = 'section.block { padding-top: '.self::SPACINGS[$design['spacing']]['value'].'; padding-bottom: '.self::SPACINGS[$design['spacing']]['value'].'; }';
        }

        return implode("\n", $rules);
    }

    /**
     * The Google Fonts stylesheet URL for the chosen font, if it needs one.
     *
     * @param  array<string, mixed>  $content
     */
    public static function fontsUrl(array $content): ?string
    {
        $key = self::clean($content['design'] ?? null)['font'] ?? null;
        $google = $key ? self::FONTS[$key]['google'] : null;

        return $google ? 'https://fonts.googleapis.com/css2?family='.$google.'&display=swap' : null;
    }
}

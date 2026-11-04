<?php

namespace App\Services\SiteBuilder;

/**
 * Apply deterministic hero overlay + background CSS when the user asks for a photo hero.
 *
 * @param  array<string, mixed>  $content
 * @return array<string, mixed>
 */
class SiteEditHeroEnsurer
{
    public static function apply(array $content, string $instruction): array
    {
        if (! SiteEditIntent::wantsHeroPhotoBackground($instruction)
            && ! SiteEditIntent::wantsFullWidthHero($instruction)) {
            return $content;
        }

        $variants = SectionVariants::withDefaults($content['variants'] ?? []);
        $variants['hero'] = 'overlay';
        $content['variants'] = SectionVariants::clean($variants);

        $sectionCss = SiteSectionCss::clean($content['section_css'] ?? null);
        $sectionCss['hero'] = SiteSectionCssPresets::heroPhotoBackground();
        $content['section_css'] = $sectionCss;

        return $content;
    }
}

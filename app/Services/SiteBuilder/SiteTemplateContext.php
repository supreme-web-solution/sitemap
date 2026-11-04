<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;

/**
 * Template-first context for edit agents (theme + catalog design + variants).
 */
class SiteTemplateContext
{
    public static function forSite(Site $site): string
    {
        $site->loadMissing(['lead', 'designTemplate']);

        $content = $site->content;
        $variants = SectionVariants::merge($content['variants'] ?? null, $site->designTemplate?->variants);
        $template = $site->template->value;
        $designName = $site->designTemplate?->name ?? 'Default catalog design';

        $lines = [
            'Blade theme (colours, fonts, base CSS): '.$template,
            'Catalog design template: '.$designName,
            'Layout variant: '.($site->designTemplate?->layout_variant?->value ?? 'standard'),
            'Accent: '.($content['accent'] ?? '#2563eb'),
            'Design tokens: font='.($content['design']['font'] ?? 'theme default')
                .', radius='.($content['design']['radius'] ?? 'theme default')
                .', spacing='.($content['design']['spacing'] ?? 'theme default'),
            'Do not change template unless the user explicitly asks to switch theme.',
            'Prefer variants.* and section_css.* over changing template.',
            'Active section layout presets:',
        ];

        foreach (SectionVariants::CATALOG as $section => $definition) {
            $active = $variants[$section] ?? $definition['default'];
            $lines[] = "- {$section}: {$active} (options: ".implode(', ', array_keys($definition['variants'])).')';
        }

        $custom = array_keys($content['custom_sections'] ?? []);

        if ($custom !== []) {
            $lines[] = 'Custom homepage blocks: '.implode(', ', $custom);
        }

        return implode("\n", $lines);
    }
}

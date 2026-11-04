<?php

namespace App\Services\SiteBuilder;

use App\Enums\SiteSection;
use App\Services\Ai\AiAvailability;
use App\Services\Ai\AiProviderErrors;

/**
 * Human-readable summary of what changed after an AI edit.
 */
class SiteEditSummary
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function describe(
        array $before,
        array $after,
        ?string $userInstruction = null,
        bool $aiQuotaFailure = false,
    ): string {
        $lines = [];

        foreach (SiteSectionCss::TARGETS as $key => $definition) {
            $old = SiteSectionCss::clean($before['section_css'] ?? null)[$key] ?? '';
            $new = SiteSectionCss::clean($after['section_css'] ?? null)[$key] ?? '';

            if ($old === $new) {
                continue;
            }

            if ($new === '') {
                continue;
            }

            $lines[] = __('• Wrote custom CSS for :section', [
                'section' => $definition['label'],
            ]);
        }

        foreach (SectionVariants::CATALOG as $key => $definition) {
            $old = SectionVariants::clean($before['variants'] ?? [])[$key] ?? $definition['default'];
            $new = SectionVariants::clean($after['variants'] ?? [])[$key] ?? $old;

            if ($old === $new) {
                continue;
            }

            $oldLabel = $definition['variants'][$old] ?? $old;
            $newLabel = $definition['variants'][$new] ?? $new;

            $lines[] = __('• :section layout preset: :from → :to', [
                'section' => $definition['label'],
                'from' => $oldLabel,
                'to' => $newLabel,
            ]);
        }

        $beforeServices = $before['services'] ?? [];
        $afterServices = $after['services'] ?? [];

        if (is_array($beforeServices) && is_array($afterServices)) {
            $imageAdds = 0;

            foreach ($afterServices as $index => $service) {
                if (! is_array($service)) {
                    continue;
                }

                $afterPath = is_string($service['image_path'] ?? null) ? $service['image_path'] : '';
                $beforePath = is_string($beforeServices[$index]['image_path'] ?? null)
                    ? $beforeServices[$index]['image_path']
                    : '';

                if ($afterPath !== '' && $afterPath !== $beforePath) {
                    $imageAdds++;
                }
            }

            if ($imageAdds > 0) {
                $lines[] = __('• Added :count unique photo(s) on service cards', ['count' => $imageAdds]);
            }
        }

        foreach (['headline', 'tagline', 'cta_label'] as $field) {
            $old = (string) ($before[$field] ?? '');
            $new = (string) ($after[$field] ?? '');

            if ($old !== '' && $new !== '' && $old !== $new) {
                $lines[] = __('• Updated :field', [
                    'field' => str_replace('_', ' ', $field),
                ]);
            }
        }

        $beforeSections = array_values($before['sections'] ?? []);
        $afterSections = array_values($after['sections'] ?? []);

        foreach (array_diff($afterSections, $beforeSections) as $section) {
            $label = SiteSection::tryFrom($section)?->label() ?? $section;
            $lines[] = __('• Added :section to the homepage', ['section' => $label]);
        }

        foreach (array_diff($beforeSections, $afterSections) as $section) {
            $label = SiteSection::tryFrom($section)?->label() ?? $section;
            $lines[] = __('• Removed :section from the homepage', ['section' => $label]);
        }

        if ($lines === []) {
            if (SiteStructuralIntent::wantsAddSection((string) $userInstruction)) {
                foreach (SiteChatInstruction::mentionedSections((string) $userInstruction) as $section) {
                    if (in_array($section, $beforeSections, true)) {
                        $label = SiteSection::tryFrom($section)?->label() ?? $section;

                        if ($section === SiteSection::Reviews->value) {
                            return __(':section is already on the homepage. Open the Reviews panel for this lead and fetch Google reviews so the block has content to show.', [
                                'section' => $label,
                            ]);
                        }

                        return __(':section is already on the homepage. Describe a visual change (e.g. modernize the hero) or quote new copy.', [
                            'section' => $label,
                        ]);
                    }
                }
            }

            if ($aiQuotaFailure) {
                return __('Nothing changed on the site. :hint', [
                    'hint' => AiProviderErrors::siteEditPartialUnavailableMessage(),
                ]);
            }

            $hint = AiAvailability::anthropicConfigured()
                ? __('Try naming a section (reviews, hero, footer…) and what to change, or quote new copy (e.g. change the headline to “…”).')
                : __('Add ANTHROPIC_API_KEY to your .env for full AI planning and design CSS.');

            return __('Nothing changed on the site. :hint', ['hint' => $hint]);
        }

        $intro = __('Design requests generate scoped CSS for that section only. Copy changes use the text planner. Other sections stay on the same theme.');

        return $intro."\n\n".__('Here is what changed:')."\n".implode("\n", $lines);
    }
}

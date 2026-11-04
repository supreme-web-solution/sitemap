<?php

namespace App\Services\SiteBuilder;

/**
 * Editable site document paths for AI chat (same contract as the visual editor).
 *
 * Krikkit Lab mutates files with write_file; sitemap mutates the site JSON with set/insert/move ops.
 */
class SiteEditOpCatalog
{
    /**
     * Compact reference for the edit planner agent.
     */
    public static function plannerReference(): string
    {
        $variantLines = [];

        foreach (SectionVariants::CATALOG as $section => $definition) {
            $ids = implode(', ', array_keys($definition['variants']));
            $variantLines[] = "- variants.{$section}: {$ids}";
        }

        $cssKeys = implode(', ', SiteSectionCss::keys());

        return implode("\n", [
            'Prefer document_ops (direct sets) over copy_instruction + design_css_targets when you know the exact value.',
            'Each op: { "op": "set", "path": "<path>", "value": <json value> }',
            'Copy paths: headline (max 70 chars), tagline, cta_label, about',
            'Visual paths: accent (#rrggbb), design.font, design.radius, design.spacing',
            'Section layout presets:',
            ...$variantLines,
            "Scoped CSS patches: section_css.<key> where key is one of: {$cssKeys}",
            'Hero markup uses .hero > .container > h1, p, .hero-actions, .rating — never invent .hero-inner.',
            'List ops: insert/remove/move on path sections (homepage section order).',
            'Custom homepage blocks: create_custom_home_section or set custom_sections.{id} and add section key custom:{id}.',
            'When document_ops fully satisfy the user, leave copy_instruction empty and design_css_targets [].',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     * @return list<array<string, mixed>>
     */
    public static function sanitize(array $ops): array
    {
        $clean = [];

        foreach ($ops as $op) {
            if (! is_array($op)) {
                continue;
            }

            $kind = (string) ($op['op'] ?? '');

            if ($kind === 'set') {
                $path = (string) ($op['path'] ?? '');

                if (! SiteEditApplier::isEditablePath($path)) {
                    continue;
                }

                $value = $op['value'] ?? null;

                if (preg_match('/^section_css\.([a-z_]+)$/', $path, $match) === 1) {
                    $target = $match[1];

                    if (! is_string($value) || SiteSectionCss::validate($target, SiteSectionCss::sanitize($value)) !== []) {
                        continue;
                    }

                    $value = SiteSectionCss::sanitize($value);
                }

                if (preg_match('/^variants\.([a-z_]+)$/', $path, $match) === 1) {
                    $section = $match[1];
                    $variant = is_string($value) ? trim($value) : '';

                    if (! isset(SectionVariants::CATALOG[$section]['variants'][$variant])) {
                        continue;
                    }
                }

                if (preg_match('/^custom_sections\.([a-z0-9-]+)$/', $path, $match) === 1 && is_array($value)) {
                    $value = [
                        'label' => trim((string) ($value['label'] ?? 'Custom section')),
                        'headline' => trim((string) ($value['headline'] ?? '')),
                        'body' => trim((string) ($value['body'] ?? '')),
                    ];

                    if ($value['headline'] === '' || $value['body'] === '') {
                        continue;
                    }
                }

                $clean[] = ['op' => 'set', 'path' => $path, 'value' => $value];

                continue;
            }

            if (in_array($kind, ['insert', 'remove', 'move'], true)) {
                $clean[] = $op;
            }
        }

        return $clean;
    }

    /**
     * Section keys that receive section_css via a set op.
     *
     * @param  list<array<string, mixed>>  $ops
     * @return list<string>
     */
    public static function sectionCssTargetsFromOps(array $ops): array
    {
        $keys = [];

        foreach ($ops as $op) {
            if (($op['op'] ?? '') !== 'set') {
                continue;
            }

            $path = (string) ($op['path'] ?? '');

            if (preg_match('/^section_css\.([a-z_]+)$/', $path, $match) === 1) {
                $keys[] = $match[1];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     * @return list<string>
     */
    public static function variantSectionsFromOps(array $ops): array
    {
        $keys = [];

        foreach ($ops as $op) {
            if (($op['op'] ?? '') !== 'set') {
                continue;
            }

            $path = (string) ($op['path'] ?? '');

            if (preg_match('/^variants\.([a-z_]+)$/', $path, $match) === 1) {
                $keys[] = $match[1];
            }
        }

        return array_values(array_unique($keys));
    }
}

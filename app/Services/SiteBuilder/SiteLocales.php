<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;
use Illuminate\Support\Arr;

/**
 * Locale prefixes, translated copy overlays, and hreflang helpers for multilingual sites.
 */
class SiteLocales
{
    public const DEFAULT = 'en';

    /**
     * Top-level fields that may be overridden per locale.
     *
     * @var list<string>
     */
    public const ROOT_FIELDS = ['headline', 'tagline', 'cta_label', 'about'];

    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return config('sites.locales', []);
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::catalog());
    }

    public static function isSupported(string $locale): bool
    {
        return array_key_exists($locale, self::catalog());
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function default(array $content): string
    {
        $locale = strtolower((string) ($content['default_locale'] ?? self::DEFAULT));

        return self::isSupported($locale) ? $locale : self::DEFAULT;
    }

    /**
     * Extra locales configured on the site (not including the default).
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public static function extra(array $content): array
    {
        $default = self::default($content);

        return array_values(array_filter(
            array_map('strtolower', (array) ($content['locales'] ?? [])),
            fn (string $code) => $code !== $default && self::isSupported($code),
        ));
    }

    /**
     * Default locale plus every extra locale that has a translation bundle.
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public static function available(array $content): array
    {
        $locales = [self::default($content)];

        foreach (self::extra($content) as $locale) {
            if (self::hasTranslation($content, $locale)) {
                $locales[] = $locale;
            }
        }

        return array_values(array_unique($locales));
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function hasTranslation(array $content, string $locale): bool
    {
        $bundle = $content['translations'][$locale] ?? null;

        return is_array($bundle) && $bundle !== [];
    }

    /**
     * Split a request path into locale and inner page path.
     *
     * @return array{locale: string, path: string}
     */
    public static function parsePath(Site $site, string $path): array
    {
        $content = $site->content;
        $default = self::default($content);
        $path = trim($path, '/');

        if ($path === '') {
            return ['locale' => $default, 'path' => ''];
        }

        $segment = strtok($path, '/');

        if (! is_string($segment) || ! in_array($segment, self::available($content), true) || $segment === $default) {
            return ['locale' => $default, 'path' => $path];
        }

        $inner = trim(substr($path, strlen($segment)), '/');

        return ['locale' => $segment, 'path' => $inner];
    }

    /**
     * Apply a locale's translation bundle onto the base content.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function merge(array $content, string $locale): array
    {
        if ($locale === self::default($content) || ! self::hasTranslation($content, $locale)) {
            return $content;
        }

        $bundle = $content['translations'][$locale];
        $merged = $content;

        foreach (self::ROOT_FIELDS as $field) {
            if (filled($bundle[$field] ?? null)) {
                $merged[$field] = $bundle[$field];
            }
        }

        if (! empty($bundle['services']) && is_array($bundle['services'])) {
            $merged['services'] = self::mergeServices($content['services'] ?? [], $bundle['services']);
        }

        if (! empty($bundle['faqs']) && is_array($bundle['faqs'])) {
            $merged['faqs'] = self::mergeFaqs($content['faqs'] ?? [], $bundle['faqs']);
        }

        if (! empty($bundle['copy']) && is_array($bundle['copy'])) {
            $merged['copy'] = array_merge($content['copy'] ?? [], SiteCopy::clean($bundle['copy']));
        }

        if (! empty($bundle['custom_pages']) && is_array($bundle['custom_pages'])) {
            $merged['custom_pages'] = self::mergeCustomPages($content['custom_pages'] ?? [], $bundle['custom_pages']);
        }

        if (! empty($bundle['page_labels']) && is_array($bundle['page_labels'])) {
            $merged['page_labels'] = array_merge($content['page_labels'] ?? [], $bundle['page_labels']);
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $overrides
     * @return list<array<string, mixed>>
     */
    protected static function mergeServices(array $base, array $overrides): array
    {
        $merged = [];

        foreach (array_values($base) as $index => $service) {
            $patch = $overrides[$index] ?? [];
            $entry = $service;

            foreach (['title', 'description', 'body'] as $field) {
                if (filled($patch[$field] ?? null)) {
                    $entry[$field] = $patch[$field];
                }
            }

            $merged[] = $entry;
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $overrides
     * @return list<array<string, mixed>>
     */
    protected static function mergeFaqs(array $base, array $overrides): array
    {
        $merged = [];

        foreach (array_values($base) as $index => $faq) {
            $patch = $overrides[$index] ?? [];
            $entry = $faq;

            foreach (['question', 'answer'] as $field) {
                if (filled($patch[$field] ?? null)) {
                    $entry[$field] = $patch[$field];
                }
            }

            $merged[] = $entry;
        }

        return $merged;
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $overrides
     * @return list<array<string, mixed>>
     */
    protected static function mergeCustomPages(array $base, array $overrides): array
    {
        $bySlug = collect($overrides)->filter(fn ($row) => filled($row['slug'] ?? null))->keyBy('slug');

        return array_values(array_map(function (array $page) use ($bySlug) {
            $patch = $bySlug->get($page['slug'] ?? '');

            if (! is_array($patch)) {
                return $page;
            }

            foreach (['title', 'headline', 'intro', 'body', 'cta_label'] as $field) {
                if (filled($patch[$field] ?? null)) {
                    $page[$field] = $patch[$field];
                }
            }

            return $page;
        }, $base));
    }

    /**
     * Navigation label for a standard page key in the active locale.
     *
     * @param  array<string, mixed>  $content
     */
    public static function pageLabel(array $content, string $page): string
    {
        $labels = $content['page_labels'] ?? [];

        if (filled($labels[$page] ?? null)) {
            return (string) $labels[$page];
        }

        if ($page === 'home') {
            return 'Home';
        }

        return SitePages::PAGE_LABELS[$page] ?? ucfirst($page);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array{code: string, label: string, url: string}>
     */
    public static function switcher(Site $site, SiteUrls $urls, string $pagePath): array
    {
        $items = [];

        foreach (self::available($site->content) as $code) {
            $items[] = [
                'code' => $code,
                'label' => self::catalog()[$code] ?? strtoupper($code),
                'url' => $urls->forLocale($code)->to($pagePath),
            ];
        }

        return $items;
    }

    /**
     * Validation rules for locale configuration stored on the site.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $codes = self::codes();

        return [
            'default_locale' => ['sometimes', 'string', 'in:'.implode(',', $codes)],
            'locales' => ['sometimes', 'array', 'max:'.(int) config('sites.max_extra_locales', 3)],
            'locales.*' => ['required', 'distinct', 'string', 'in:'.implode(',', $codes)],
            'translations' => ['sometimes', 'array'],
            'translations.*' => ['array'],
            'translations.*.headline' => ['nullable', 'string', 'max:'.SiteContentSchema::HEADLINE_MAX],
            'translations.*.tagline' => ['nullable', 'string', 'max:200'],
            'translations.*.cta_label' => ['nullable', 'string', 'max:40'],
            'translations.*.about' => ['nullable', 'string', 'max:2000'],
            'translations.*.services' => ['sometimes', 'array'],
            'translations.*.services.*.title' => ['nullable', 'string', 'max:60'],
            'translations.*.services.*.description' => ['nullable', 'string', 'max:250'],
            'translations.*.services.*.body' => ['nullable', 'string', 'max:5000'],
            'translations.*.faqs' => ['sometimes', 'array'],
            'translations.*.faqs.*.question' => ['nullable', 'string', 'max:150'],
            'translations.*.faqs.*.answer' => ['nullable', 'string', 'max:600'],
            'translations.*.copy' => ['sometimes', 'array'],
            'translations.*.copy.*' => ['nullable', 'string', 'max:'.SiteCopy::MAX_LENGTH],
            'translations.*.page_labels' => ['sometimes', 'array'],
            'translations.*.page_labels.*' => ['nullable', 'string', 'max:40'],
            'translations.*.custom_pages' => ['sometimes', 'array'],
            'translations.*.custom_pages.*.slug' => ['required', 'string', 'max:60'],
            'translations.*.custom_pages.*.title' => ['nullable', 'string', 'max:120'],
            'translations.*.custom_pages.*.headline' => ['nullable', 'string', 'max:120'],
            'translations.*.custom_pages.*.intro' => ['nullable', 'string', 'max:250'],
            'translations.*.custom_pages.*.body' => ['nullable', 'string', 'max:8000'],
            'translations.*.custom_pages.*.cta_label' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function applyValidated(array $validated, array $content): array
    {
        if (array_key_exists('default_locale', $validated)) {
            $content['default_locale'] = strtolower($validated['default_locale']);
        }

        if (array_key_exists('locales', $validated)) {
            $content['locales'] = array_values(array_unique(array_map(
                'strtolower',
                array_filter($validated['locales'] ?? [], fn ($code) => is_string($code) && self::isSupported($code)),
            )));
        }

        if (array_key_exists('translations', $validated)) {
            $content['translations'] = Arr::only(
                array_merge($content['translations'] ?? [], $validated['translations'] ?? []),
                self::extra($content),
            );
        }

        return $content;
    }
}

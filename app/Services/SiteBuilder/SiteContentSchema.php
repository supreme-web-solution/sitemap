<?php

namespace App\Services\SiteBuilder;

use App\Enums\ReviewsStyle;
use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class SiteContentSchema
{
    public const HEADLINE_MAX = 160;

    public const SERVICE_DESCRIPTION_MAX = 500;

    public const MAX_SERVICES = 8;

    public const MAX_FAQS = 10;

    /**
     * Content keys owned by the visual editor rather than by the AI.
     *
     * @var list<string>
     */
    public const EDITOR_FIELDS = ['copy', 'design', 'section_styles', 'section_css', 'variants', 'process', 'why', 'default_locale', 'locales', 'translations', 'custom_pages', 'page_labels', 'custom_sections', 'hidden_sections', 'images', 'text_styles'];

    /**
     * Validation rules for a site specification.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'template' => ['required', Rule::enum(SiteTemplate::class)],
            'accent' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'headline' => ['required', 'string', 'max:'.self::HEADLINE_MAX],
            'tagline' => ['required', 'string', 'max:200'],
            'cta_label' => ['required', 'string', 'max:40'],
            'about' => ['required', 'string', 'max:2000'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => [
                'required',
                'distinct',
                'string',
                'max:72',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        $fail(__('Invalid homepage section.'));

                        return;
                    }

                    if (SiteSection::tryFrom($value) !== null || CustomHomeSection::isKey($value)) {
                        return;
                    }

                    $fail(__('Unknown homepage section: :section.', ['section' => $value]));
                },
            ],
            'custom_sections' => ['sometimes', 'nullable', 'array', 'max:8'],
            'custom_sections.*' => ['required', 'array'],
            'custom_sections.*.label' => ['required', 'string', 'max:40'],
            'custom_sections.*.headline' => ['required', 'string', 'max:120'],
            'custom_sections.*.body' => ['required', 'string', 'max:4000'],
            'reviews_style' => ['sometimes', 'nullable', Rule::enum(ReviewsStyle::class)],
            'hidden_sections' => ['sometimes', 'array'],
            'hidden_sections.*' => ['string', 'distinct', Rule::in(SiteSection::defaultOrder())],
            'services' => ['required', 'array', 'min:1', 'max:'.self::MAX_SERVICES],
            'services.*.title' => ['required', 'string', 'max:60'],
            'services.*.description' => ['required', 'string', 'max:'.self::SERVICE_DESCRIPTION_MAX],
            'services.*.slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'services.*.body' => ['nullable', 'string', 'max:5000'],
            'additional_services' => ['sometimes', 'array', 'max:8'],
            'additional_services.*' => ['string', 'max:60', 'distinct'],
            'services.*.image_path' => ['nullable', 'string', 'max:255'],
            'images' => ['sometimes', 'nullable', 'array:'.implode(',', SiteImagePlacements::KEYS)],
            'images.*' => ['nullable', 'string', 'max:255'],
            'pages' => ['sometimes', 'array'],
            'pages.*' => ['required', 'distinct', Rule::in(SitePages::OPTIONAL)],
            'faqs' => ['present', 'array', 'max:'.self::MAX_FAQS],
            'faqs.*.question' => ['required', 'string', 'max:150'],
            'faqs.*.answer' => ['required', 'string', 'max:600'],
            'copy' => ['sometimes', 'nullable', 'array:'.implode(',', SiteCopy::keys())],
            'copy.*' => ['nullable', 'string', 'max:'.SiteCopy::MAX_LENGTH],
            'design' => ['sometimes', 'nullable', 'array:'.implode(',', SiteDesign::KEYS)],
            'design.font' => ['nullable', Rule::in(SiteDesign::options()['font'])],
            'design.radius' => ['nullable', Rule::in(SiteDesign::options()['radius'])],
            'design.spacing' => ['nullable', Rule::in(SiteDesign::options()['spacing'])],
            'section_styles' => ['sometimes', 'nullable', 'array:'.implode(',', array_column(SiteSection::cases(), 'value'))],
            'section_styles.*' => ['nullable', Rule::in(SiteDesign::SECTION_STYLES)],
            'text_styles' => ['sometimes', 'nullable', 'array'],
            'variants' => ['sometimes', 'nullable', 'array:'.implode(',', array_keys(SectionVariants::CATALOG))],
            'section_css' => ['sometimes', 'nullable', 'array:'.implode(',', SiteSectionCss::keys())],
            'section_css.*' => ['nullable', 'string', 'max:'.SiteSectionCss::MAX_LENGTH],
            ...self::variantRules(),
            'process' => ['sometimes', 'array', 'max:6'],
            'process.*.title' => ['required', 'string', 'max:60'],
            'process.*.description' => ['required', 'string', 'max:250'],
            'why' => ['sometimes', 'array', 'max:6'],
            'why.*.title' => ['required', 'string', 'max:80'],
            'why.*.description' => ['required', 'string', 'max:250'],
            'page_labels' => ['sometimes', 'nullable', 'array'],
            'page_labels.*' => ['nullable', 'string', 'max:40'],
            'custom_pages' => ['sometimes', 'array', 'max:12'],
            'custom_pages.*.kind' => ['required', Rule::in(['landing', 'location'])],
            'custom_pages.*.slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'custom_pages.*.title' => ['required', 'string', 'max:120'],
            'custom_pages.*.headline' => ['required', 'string', 'max:120'],
            'custom_pages.*.intro' => ['nullable', 'string', 'max:250'],
            'custom_pages.*.body' => ['required', 'string', 'max:8000'],
            'custom_pages.*.cta_label' => ['nullable', 'string', 'max:40'],
            'custom_pages.*.city' => ['nullable', 'string', 'max:80'],
            ...SiteLocales::rules(),
        ];
    }

    /**
     * Carry the agency's hand-made edits (reworded labels, design tokens, section styles) into
     * content produced by the AI, which only knows about the generated fields.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $generated
     * @return array<string, mixed>
     */
    public static function addCustomPageHooks(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            self::validateCustomPageSlugs($v->getData(), $v);
            self::validateCustomHomeSections($v->getData(), $v);
        });
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function validateCustomHomeSections(array $document, Validator $validator): void
    {
        $blocks = $document['custom_sections'] ?? [];

        foreach ($document['sections'] ?? [] as $index => $section) {
            if (! is_string($section) || ! CustomHomeSection::isKey($section)) {
                continue;
            }

            $id = CustomHomeSection::idFromKey($section);
            $block = $blocks[$id] ?? null;

            if (! is_array($block) || trim((string) ($block['headline'] ?? '')) === '') {
                $validator->errors()->add(
                    "sections.{$index}",
                    __('Custom section :section is missing content.', ['section' => $section]),
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function validateCustomPageSlugs(array $document, Validator $validator): void
    {
        $seen = [];

        foreach ($document['custom_pages'] ?? [] as $index => $page) {
            if (! is_array($page)) {
                continue;
            }

            $kind = (string) ($page['kind'] ?? '');
            $slug = (string) ($page['slug'] ?? '');
            $key = "{$kind}:{$slug}";

            if ($slug === '' || $kind === '') {
                continue;
            }

            if (isset($seen[$key])) {
                $validator->errors()->add(
                    "custom_pages.{$index}.slug",
                    __('Each :kind page needs a unique URL slug.', ['kind' => $kind]),
                );
            }

            $seen[$key] = true;
        }
    }

    public static function preserveEditorFields(array $previous, array $generated): array
    {
        foreach (self::EDITOR_FIELDS as $field) {
            if (! array_key_exists($field, $generated) && ! empty($previous[$field])) {
                $generated[$field] = $previous[$field];
            }
        }

        return $generated;
    }

    /**
     * Normalize validated input into the stored content shape.
     *
     * Service slugs and the page list are only stored when given, unless $withDefaults is set
     * (used for freshly generated sites so every service gets a stable address).
     *
     * @param  array<string, mixed>  $validated
     * @return array{accent: string, headline: string, tagline: string, cta_label: string, about: string, sections: list<string>, reviews_style?: string, pages?: list<string>, services: list<array{title: string, description: string, slug?: string, body?: string, image_path?: string}>, faqs: list<array{question: string, answer: string}>, images?: array<string, string>}
     */
    public static function toContent(array $validated, bool $withDefaults = false): array
    {
        $taken = [];

        $services = [];

        foreach (array_values($validated['services']) as $service) {
            $entry = ['title' => $service['title'], 'description' => $service['description']];

            if (filled($service['slug'] ?? null) || $withDefaults) {
                $entry['slug'] = SitePages::uniqueSlug($service['slug'] ?? null, $service['title'], $taken);
                $taken[] = $entry['slug'];
            }

            if (filled($service['body'] ?? null)) {
                $entry['body'] = $service['body'];
            }

            if (filled($service['image_path'] ?? null)) {
                $entry['image_path'] = $service['image_path'];
            }

            $services[] = $entry;
        }

        $additional = [];

        foreach (array_values($validated['additional_services'] ?? []) as $title) {
            $title = trim((string) $title);

            if ($title !== '') {
                $additional[] = $title;
            }
        }

        $content = [
            'accent' => strtolower($validated['accent']),
            'headline' => $validated['headline'],
            'tagline' => $validated['tagline'],
            'cta_label' => $validated['cta_label'],
            'about' => $validated['about'],
            'sections' => array_values($validated['sections']),
            'services' => $services,
            'faqs' => array_values(array_map(
                fn (array $faq) => ['question' => $faq['question'], 'answer' => $faq['answer']],
                $validated['faqs'],
            )),
        ];

        if (array_key_exists('additional_services', $validated)) {
            $content['additional_services'] = $additional;
        }

        if (filled($validated['reviews_style'] ?? null)) {
            $content['reviews_style'] = $validated['reviews_style'];
        }

        if (array_key_exists('hidden_sections', $validated)) {
            $content['hidden_sections'] = array_values($validated['hidden_sections'] ?? []);
        }

        if (array_key_exists('pages', $validated)) {
            $content['pages'] = array_values($validated['pages'] ?? []);
        } elseif ($withDefaults) {
            $content['pages'] = SitePages::OPTIONAL;
        }

        if ($copy = SiteCopy::clean($validated['copy'] ?? null)) {
            $content['copy'] = $copy;
        }

        if ($design = SiteDesign::clean($validated['design'] ?? null)) {
            $content['design'] = $design;
        }

        if ($styles = SiteDesign::cleanSectionStyles($validated['section_styles'] ?? null)) {
            $content['section_styles'] = $styles;
        }

        if (is_array($validated['text_styles'] ?? null)) {
            $textStyles = SiteTextStyles::clean($validated['text_styles']);

            if ($textStyles !== null) {
                $content['text_styles'] = $textStyles;
            }
        }

        if ($variants = SectionVariants::clean($validated['variants'] ?? null)) {
            $content['variants'] = $variants;
        }

        if ($sectionCss = SiteSectionCss::clean($validated['section_css'] ?? null)) {
            $content['section_css'] = $sectionCss;
        }

        if (is_array($validated['custom_sections'] ?? null) && $validated['custom_sections'] !== []) {
            $content['custom_sections'] = $validated['custom_sections'];
        }

        foreach (['process', 'why'] as $list) {
            if (array_key_exists($list, $validated)) {
                $content[$list] = array_values(array_map(
                    fn (array $item) => ['title' => $item['title'], 'description' => $item['description']],
                    $validated[$list] ?? [],
                ));
            }
        }

        if (! empty($validated['page_labels'])) {
            $content['page_labels'] = array_filter($validated['page_labels'], fn ($label) => filled($label));
        }

        if (array_key_exists('custom_pages', $validated)) {
            $content['custom_pages'] = array_values(array_map(
                fn (array $page) => array_filter([
                    'kind' => $page['kind'],
                    'slug' => $page['slug'],
                    'title' => $page['title'],
                    'headline' => $page['headline'],
                    'intro' => $page['intro'] ?? null,
                    'body' => $page['body'],
                    'cta_label' => $page['cta_label'] ?? null,
                    'city' => $page['city'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
                $validated['custom_pages'] ?? [],
            ));
        }

        if (is_array($validated['images'] ?? null)) {
            $images = [];

            foreach (SiteImagePlacements::KEYS as $key) {
                $path = $validated['images'][$key] ?? null;

                if (is_string($path) && $path !== '') {
                    $images[$key] = $path;
                }
            }

            if ($images !== []) {
                $content['images'] = $images;
            }
        }

        return SiteLocales::applyValidated($validated, $content);
    }

    /**
     * Remember that a homepage section was removed on purpose, so it is not put back automatically.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function hideSection(array $content, string $key): array
    {
        if (SiteSection::tryFrom($key) === null) {
            return $content;
        }

        $hidden = array_values(array_unique([
            ...array_values(array_filter((array) ($content['hidden_sections'] ?? []), 'is_string')),
            $key,
        ]));

        $content['hidden_sections'] = $hidden;

        return $content;
    }

    /**
     * A section that is added back is no longer hidden.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function showSection(array $content, string $key): array
    {
        if (! isset($content['hidden_sections']) || ! is_array($content['hidden_sections'])) {
            return $content;
        }

        $content['hidden_sections'] = array_values(array_filter(
            $content['hidden_sections'],
            fn (mixed $item): bool => $item !== $key,
        ));

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function variantRules(): array
    {
        $rules = [];

        foreach (SectionVariants::CATALOG as $section => $definition) {
            $rules['variants.'.$section] = ['sometimes', 'nullable', Rule::in(array_keys($definition['variants']))];
        }

        return $rules;
    }
}

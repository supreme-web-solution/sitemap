<?php

namespace App\Services\SiteBuilder;

use App\Enums\ReviewsStyle;
use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class SiteContentSchema
{
    public const HEADLINE_MAX = 70;

    public const MAX_SERVICES = 8;

    public const MAX_FAQS = 10;

    /**
     * Content keys owned by the visual editor rather than by the AI.
     *
     * @var list<string>
     */
    public const EDITOR_FIELDS = ['copy', 'design', 'section_styles', 'default_locale', 'locales', 'translations', 'custom_pages', 'page_labels'];

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
            'sections.*' => ['required', 'distinct', Rule::enum(SiteSection::class)],
            'reviews_style' => ['sometimes', 'nullable', Rule::enum(ReviewsStyle::class)],
            'services' => ['required', 'array', 'min:1', 'max:'.self::MAX_SERVICES],
            'services.*.title' => ['required', 'string', 'max:60'],
            'services.*.description' => ['required', 'string', 'max:250'],
            'services.*.slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'services.*.body' => ['nullable', 'string', 'max:5000'],
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
        });
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
     * @return array{accent: string, headline: string, tagline: string, cta_label: string, about: string, sections: list<string>, reviews_style?: string, pages?: list<string>, services: list<array{title: string, description: string, slug?: string, body?: string}>, faqs: list<array{question: string, answer: string}>}
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

            $services[] = $entry;
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

        if (filled($validated['reviews_style'] ?? null)) {
            $content['reviews_style'] = $validated['reviews_style'];
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

        return SiteLocales::applyValidated($validated, $content);
    }
}

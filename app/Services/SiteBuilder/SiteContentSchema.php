<?php

namespace App\Services\SiteBuilder;

use App\Enums\ReviewsStyle;
use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use Illuminate\Validation\Rule;

class SiteContentSchema
{
    public const HEADLINE_MAX = 70;

    public const MAX_SERVICES = 8;

    public const MAX_FAQS = 10;

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
        ];
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

        return $content;
    }
}

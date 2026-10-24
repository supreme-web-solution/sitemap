<?php

namespace App\Services\SiteBuilder;

/**
 * Fixed headings and labels on a generated site that the agency can reword.
 *
 * Each key stores an override in the site content under `copy`. Without an override the
 * template's own wording is used, so removing an override always restores the default.
 */
class SiteCopy
{
    public const MAX_LENGTH = 300;

    /**
     * Every editable label, keyed by its stored name.
     *
     * @var array<string, array{label: string, max: int, multiline: bool}>
     */
    public const FIELDS = [
        'call_label' => ['label' => 'Call button', 'max' => 30, 'multiline' => false],
        'about_label' => ['label' => 'About small heading', 'max' => 40, 'multiline' => false],
        'about_title' => ['label' => 'About heading', 'max' => 120, 'multiline' => false],
        'services_label' => ['label' => 'Services small heading', 'max' => 40, 'multiline' => false],
        'services_title' => ['label' => 'Services heading', 'max' => 120, 'multiline' => false],
        'faq_label' => ['label' => 'FAQ small heading', 'max' => 40, 'multiline' => false],
        'faq_title' => ['label' => 'FAQ heading', 'max' => 120, 'multiline' => false],
        'reviews_label' => ['label' => 'Reviews small heading', 'max' => 40, 'multiline' => false],
        'reviews_title' => ['label' => 'Reviews heading', 'max' => 120, 'multiline' => false],
        'gallery_label' => ['label' => 'Gallery small heading', 'max' => 40, 'multiline' => false],
        'gallery_title' => ['label' => 'Gallery heading', 'max' => 120, 'multiline' => false],
        'hours_label' => ['label' => 'Opening hours small heading', 'max' => 40, 'multiline' => false],
        'hours_title' => ['label' => 'Opening hours heading', 'max' => 120, 'multiline' => false],
        'contact_label' => ['label' => 'Contact small heading', 'max' => 40, 'multiline' => false],
        'contact_title' => ['label' => 'Contact heading', 'max' => 120, 'multiline' => false],
        'contact_intro' => ['label' => 'Contact introduction', 'max' => 200, 'multiline' => true],
        'cta_heading' => ['label' => 'Call-to-action band heading', 'max' => 120, 'multiline' => false],
        'about_page_heading' => ['label' => 'About page heading', 'max' => 120, 'multiline' => false],
        'about_page_intro' => ['label' => 'About page introduction', 'max' => 250, 'multiline' => true],
        'services_page_heading' => ['label' => 'Services page heading', 'max' => 120, 'multiline' => false],
        'services_page_intro' => ['label' => 'Services page introduction', 'max' => 250, 'multiline' => true],
        'gallery_page_heading' => ['label' => 'Gallery page heading', 'max' => 120, 'multiline' => false],
        'gallery_page_intro' => ['label' => 'Gallery page introduction', 'max' => 250, 'multiline' => true],
        'contact_page_heading' => ['label' => 'Contact page heading', 'max' => 120, 'multiline' => false],
        'contact_page_intro' => ['label' => 'Contact page introduction', 'max' => 250, 'multiline' => true],
        'blog_page_heading' => ['label' => 'Blog page heading', 'max' => 120, 'multiline' => false],
        'blog_page_intro' => ['label' => 'Blog page introduction', 'max' => 250, 'multiline' => true],
    ];

    /**
     * Get the stored names of every editable label.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::FIELDS);
    }

    /**
     * Drop unknown keys and blank values so only real overrides are stored.
     *
     * @param  array<string, mixed>|null  $copy
     * @return array<string, string>
     */
    public static function clean(?array $copy): array
    {
        $clean = [];

        foreach (self::FIELDS as $key => $field) {
            $value = $copy[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $clean[$key] = trim($value);
            }
        }

        return $clean;
    }
}

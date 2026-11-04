<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Str;

/**
 * AI-authored CSS patches scoped to one part of the site (footer, hero, etc.).
 */
class SiteSectionCss
{
    public const MAX_LENGTH = 8000;

    /**
     * @var array<string, array{label: string, scope: list<string>}>
     */
    public const TARGETS = [
        'header' => [
            'label' => 'Header / navigation',
            'scope' => ['.site-header', '.brand', '.nav', '.brand-mark', '.brand-logo'],
        ],
        'footer' => [
            'label' => 'Footer',
            'scope' => ['.site-footer', '.footer-main', '.footer-brand', '.footer-col', '.footer-bar', '.footer-cta', '.footer-nav', '.footer-hours', '.footer-contact', '.footer-social'],
        ],
        'hero' => [
            'label' => 'Hero',
            'scope' => [
                '#hero',
                '.hero',
                '.hero .container',
                '.hero h1',
                '.hero p',
                '.hero .rating',
                '.hero-actions',
                '.hero-eyebrow',
                '.hero-image',
            ],
        ],
        'services' => [
            'label' => 'Services',
            'scope' => ['#services', '.services-block'],
        ],
        'about' => [
            'label' => 'About',
            'scope' => ['#about', '.about-block'],
        ],
        'about_page' => [
            'label' => 'About page story',
            'scope' => ['.about-story-block', '.about-story', '.about-portrait'],
        ],
        'mission' => [
            'label' => 'Our mission',
            'scope' => ['#mission', '.mission-block', '.mission-grid', '.mission-body'],
        ],
        'services_page' => [
            'label' => 'Services page',
            'scope' => ['.services-page-block', '.service-features', '.service-feature'],
        ],
        'process' => [
            'label' => 'How it works',
            'scope' => ['#process', '.process-block'],
        ],
        'why' => [
            'label' => 'Why choose us',
            'scope' => ['#why', '.why-block'],
        ],
        'reviews' => [
            'label' => 'Reviews',
            'scope' => ['#reviews', '.reviews-block'],
        ],
        'gallery' => [
            'label' => 'Gallery',
            'scope' => ['#gallery', '.gallery-block', '.gallery'],
        ],
        'areas' => [
            'label' => 'Areas we serve',
            'scope' => ['#areas', '.areas-block'],
        ],
        'hours' => [
            'label' => 'Hours & map',
            'scope' => ['#visit', '.visit-block'],
        ],
        'faq' => [
            'label' => 'FAQ',
            'scope' => ['#faq', '.faq-block'],
        ],
        'contact' => [
            'label' => 'Contact',
            'scope' => ['#contact', '.contact-block'],
        ],
        'custom' => [
            'label' => 'Custom homepage blocks',
            'scope' => ['.custom-section', '.custom-section__body'],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::TARGETS);
    }

    /**
     * @param  array<string, mixed>|null  $patches
     * @return array<string, string>
     */
    public static function clean(?array $patches): array
    {
        if (! is_array($patches)) {
            return [];
        }

        $clean = [];

        foreach (self::TARGETS as $key => $definition) {
            $value = $patches[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $css = self::sanitize($value);

            if ($css !== '' && self::validate($key, $css) === []) {
                $clean[$key] = $css;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function stylesheet(array $content): string
    {
        $chunks = [];

        foreach (self::clean($content['section_css'] ?? null) as $css) {
            $chunks[] = $css;
        }

        return implode("\n\n", $chunks);
    }

    public static function sanitize(string $css): string
    {
        $css = strip_tags($css);
        $css = preg_replace('/@import\b[^;]+;/i', '', $css) ?? $css;
        $css = preg_replace('/expression\s*\(/i', '', $css) ?? $css;
        $css = preg_replace('/javascript\s*:/i', '', $css) ?? $css;
        $css = Str::limit(trim($css), self::MAX_LENGTH, '');

        return $css;
    }

    /**
     * @return list<string>
     */
    public static function validate(string $target, string $css): array
    {
        $errors = [];

        if (! isset(self::TARGETS[$target])) {
            $errors[] = 'Unknown design target.';

            return $errors;
        }

        if ($css === '') {
            $errors[] = 'CSS is empty.';

            return $errors;
        }

        if (Str::length($css) > self::MAX_LENGTH) {
            $errors[] = 'CSS is too long.';
        }

        if (preg_match('/@import|@charset|<\/?script|url\s*\(\s*["\']?\s*javascript/i', $css) === 1) {
            $errors[] = 'CSS contains disallowed directives.';
        }

        $scope = self::TARGETS[$target]['scope'];
        $usesScope = false;

        foreach ($scope as $selector) {
            if (Str::contains($css, $selector)) {
                $usesScope = true;

                break;
            }
        }

        if (! $usesScope) {
            $errors[] = 'CSS must use selectors for this section only (for example '.($scope[0] ?? '').').';
        }

        if (preg_match('/(?:^|[\{,\n])\s*(body|html|:root|main)\s*\{/im', $css) === 1) {
            $errors[] = 'Do not restyle the whole page; scope rules to this section.';
        }

        return $errors;
    }
}

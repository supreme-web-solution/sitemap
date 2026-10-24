<?php

namespace App\Services\SiteBuilder;

use App\Enums\ReviewsStyle;
use App\Enums\SiteLayoutVariant;
use App\Enums\SiteSection;
use App\Http\Controllers\Sites\PreviewSectionViewController;
use App\Models\Lead;
use App\Models\Site;
use App\Services\Preview\PreviewLink;

class SiteRenderer
{
    /**
     * Days of the week in display order.
     *
     * @var list<string>
     */
    protected const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * The fewest photos worth showing as a gallery (the first is the hero).
     */
    public const MIN_GALLERY_PHOTOS = 2;

    public function __construct(protected SitePages $pages, protected SiteSeo $seo) {}

    /**
     * Render one page of the site as a self-contained HTML document, or null when the site has no such page.
     */
    public function render(Site $site, bool $asPreview = false, string $path = '', ?SiteUrls $urls = null, bool $editor = false, bool $asStaging = false): ?string
    {
        $site->loadMissing(['lead', 'designTemplate']);

        $parsed = SiteLocales::parsePath($site, $path);
        $locale = $parsed['locale'];
        $innerPath = $parsed['path'];
        $defaultLocale = SiteLocales::default($site->content);
        $content = SiteLocales::merge($site->content, $locale);

        $renderSite = clone $site;
        $renderSite->content = $content;

        $page = $this->pages->resolve($renderSite, $innerPath);

        if ($page === null) {
            return null;
        }

        $urls ??= $asPreview
            ? SiteUrls::preview($site, request()->boolean('internal') || $editor, $editor, $locale)
            : SiteUrls::root(defaultLocale: $defaultLocale, locale: $locale);

        $editor = $editor && $asPreview;

        $lead = $site->lead;
        $photos = $lead->photoUrls();
        $hours = $this->orderedHours($lead->opening_hours);
        $mapEmbedUrl = $this->mapEmbedUrl($lead->name, $lead->address);
        $reviews = $lead->testimonials();
        $reviewsStyle = $this->reviewsStyle($content, $reviews->count());
        $seo = $this->seo->for($renderSite, $page, $urls, $asPreview, $asStaging, $content);
        $layoutVariant = $site->designTemplate?->layout_variant ?? SiteLayoutVariant::Standard;

        return view('sites.pages.'.$page['type'], [
            'site' => $renderSite,
            'lead' => $lead,
            'content' => $content,
            'page' => $page,
            'urls' => $urls,
            'seo' => $seo,
            'seoBuilder' => $this->seo,
            'breadcrumbs' => $seo['breadcrumbs'],
            'nav' => $this->navigation($renderSite, $urls, $content),
            'services' => $this->pages->services($content),
            'posts' => $page['type'] === 'blog' ? $this->pages->posts($site) : collect(),
            'sections' => $this->visibleSections($content, $photos, $hours, $mapEmbedUrl, $reviews->count()),
            'reviews' => $reviews,
            'reviewsStyle' => $reviewsStyle->value,
            'reviewsUrl' => $lead->reviewsUrl(),
            'onAccent' => $this->readableTextOn($content['accent']),
            'template' => $site->template->value,
            'layoutClass' => $layoutVariant->bodyClass(),
            'locale' => $locale,
            'localeSwitcher' => count(SiteLocales::available($site->content)) > 1
                ? SiteLocales::switcher($site, $urls, $page['path'])
                : [],
            'asPreview' => $asPreview && ! $editor,
            'editing' => $editor,
            'e' => new SiteEditing($content, $editor),
            'designCss' => SiteDesign::css($content),
            'designFontsUrl' => SiteDesign::fontsUrl($content),
            'claimEmail' => $asPreview ? $site->team->owner()?->email : null,
            'packages' => $asPreview
                ? $site->team->packages()->where('is_active', true)->orderBy('id')->get()
                : collect(),
            'commentAction' => $asPreview ? PreviewLink::action('sites.preview.comments', $site) : null,
            'packageAction' => $asPreview ? PreviewLink::action('sites.preview.packages', $site) : null,
            'internalPreview' => request()->boolean('internal') || $editor,
            'sectionBeacons' => $asPreview && ! $editor && $site->exists && ! request()->boolean('internal')
                ? collect(PreviewSectionViewController::KEYS)
                    ->mapWithKeys(fn (string $key) => [$key => PreviewLink::action('sites.preview.sections', $site, ['section' => $key])])
                    ->all()
                : [],
            'logoUrl' => $lead->logoUrl(),
            'heroImage' => $photos[0] ?? null,
            'galleryPhotos' => $photos,
            'hours' => $hours,
            'phoneHref' => $lead->phone ? 'tel:'.preg_replace('/[^\d+]/', '', $lead->phone) : null,
            'mapEmbedUrl' => $mapEmbedUrl,
            'year' => now()->year,
        ])->render();
    }

    /**
     * Build the main menu: Home, then every page the site has.
     *
     * @param  array<string, mixed>  $content
     * @return list<array{key: string, label: string, url: string}>
     */
    public function navigation(Site $site, SiteUrls $urls, array $content): array
    {
        $items = [['key' => 'home', 'label' => SiteLocales::pageLabel($content, 'home'), 'url' => $urls->to()]];

        foreach ($this->pages->available($site) as $page) {
            $items[] = [
                'key' => $page,
                'label' => SiteLocales::pageLabel($content, $page),
                'url' => $urls->to($page),
            ];
        }

        return $items;
    }

    /**
     * Resolve the planned section order, dropping sections without real content.
     *
     * @param  array<string, mixed>  $content
     * @param  list<string>  $photos
     * @param  array<string, string>  $hours
     * @return list<string>
     */
    public function visibleSections(array $content, array $photos, array $hours, ?string $mapEmbedUrl, int $reviewCount = 0): array
    {
        $planned = $content['sections'] ?? SiteSection::defaultOrder();

        $isAvailable = fn (SiteSection $section) => match ($section) {
            SiteSection::Services => ! empty($content['services']),
            SiteSection::About => filled($content['about'] ?? null),
            SiteSection::Reviews => $reviewCount >= Lead::MIN_TESTIMONIALS,
            SiteSection::Gallery => count($photos) >= self::MIN_GALLERY_PHOTOS,
            SiteSection::Hours => $hours !== [] || $mapEmbedUrl !== null,
            SiteSection::Faq => ! empty($content['faqs']),
            SiteSection::Contact => true,
        };

        $sections = array_values(array_filter(
            array_map(fn (string $value) => SiteSection::tryFrom($value), $planned),
            fn (?SiteSection $section) => $section !== null && $isAvailable($section),
        ));

        if (! in_array(SiteSection::Contact, $sections, true)) {
            $sections[] = SiteSection::Contact;
        }

        return array_values(array_unique(array_map(fn (SiteSection $section) => $section->value, $sections)));
    }

    /**
     * Pick the chosen reviews style, falling back to the carousel when there are too few reviews for it.
     *
     * @param  array<string, mixed>  $content
     */
    protected function reviewsStyle(array $content, int $reviewCount): ReviewsStyle
    {
        $style = ReviewsStyle::tryFrom((string) ($content['reviews_style'] ?? '')) ?? ReviewsStyle::Carousel;

        return $reviewCount >= $style->minimumReviews() ? $style : ReviewsStyle::Carousel;
    }

    /**
     * Sort opening hours Monday to Sunday.
     *
     * @param  array<string, string>|null  $hours
     * @return array<string, string>
     */
    protected function orderedHours(?array $hours): array
    {
        if (! $hours) {
            return [];
        }

        $ordered = [];

        foreach (self::DAYS as $day) {
            if (isset($hours[$day])) {
                $ordered[$day] = $hours[$day];
            }
        }

        return $ordered + $hours;
    }

    /**
     * Pick black or white text, whichever reads better on the given hex colour.
     */
    protected function readableTextOn(string $hex): string
    {
        [$red, $green, $blue] = array_map(
            fn (string $channel) => hexdec($channel) / 255,
            str_split(ltrim($hex, '#'), 2),
        );

        $linear = fn (float $channel) => $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;

        $luminance = 0.2126 * $linear($red) + 0.7152 * $linear($green) + 0.0722 * $linear($blue);

        return $luminance > 0.179 ? '#0c0a09' : '#ffffff';
    }

    /**
     * Build a Google Maps embed URL for the business location.
     */
    protected function mapEmbedUrl(string $name, ?string $address): ?string
    {
        if (! $address) {
            return null;
        }

        return 'https://maps.google.com/maps?output=embed&q='.rawurlencode($name.', '.$address);
    }
}

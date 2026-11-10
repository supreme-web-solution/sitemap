<?php

namespace App\Services\SiteBuilder;

use App\Enums\ProposalStatus;
use App\Enums\ReviewsStyle;
use App\Enums\SiteLayoutVariant;
use App\Enums\SiteSection;
use App\Http\Controllers\Sites\PreviewSectionViewController;
use App\Models\Lead;
use App\Models\Site;
use App\Services\Media\SiteImageStorage;
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

    public function __construct(
        protected SitePages $pages,
        protected SiteSeo $seo,
        protected SiteBrandAssets $brandAssets,
    ) {}

    /**
     * Render one page of the site as a self-contained HTML document, or null when the site has no such page.
     */
    public function render(Site $site, bool $asPreview = false, string $path = '', ?SiteUrls $urls = null, bool $editor = false, bool $asStaging = false, bool $blockIndexing = false): ?string
    {
        $site->loadMissing(['lead', 'designTemplate', 'assistant']);

        $parsed = SiteLocales::parsePath($site, $path);
        $locale = $parsed['locale'];
        $innerPath = $parsed['path'];
        $defaultLocale = SiteLocales::default($site->content);
        $content = SiteLocales::merge($site->content, $locale);
        $lead = $site->lead;
        $editing = $editor && $asPreview;

        if (! $editing) {
            $content = (new SiteServiceCopy)->ensureReady(
                $lead,
                $content,
                app(RuleBasedCopywriter::class)->serviceFillers($lead->category),
            );
        }

        $renderSite = clone $site;
        $renderSite->content = $content;

        $page = $this->pages->resolve($renderSite, $innerPath);

        if ($page === null) {
            return null;
        }

        $urls ??= $asPreview
            ? SiteUrls::preview($site, request()->boolean('internal') || $editing, $editing, $locale)
            : SiteUrls::root(defaultLocale: $defaultLocale, locale: $locale);

        $editor = $editing;
        $photos = $lead->photoUrls();
        $hours = $this->orderedHours($lead->opening_hours);
        $mapEmbedUrl = $this->mapEmbedUrl($lead->name, $lead->address);
        $reviews = $lead->testimonials();
        $reviewsStyle = $this->reviewsStyle($content, $reviews->count());
        if (! $this->brandAssets->present($site->content ?? []) && $site->exists) {
            $site = $this->brandAssets->ensure($site->fresh(['lead']));
            $content = SiteLocales::merge($site->content, $locale);

            if (! $editing) {
                $content = (new SiteServiceCopy)->ensureReady(
                    $lead,
                    $content,
                    app(RuleBasedCopywriter::class)->serviceFillers($lead->category),
                );
            }

            $renderSite = clone $site;
            $renderSite->content = $content;
        }

        $seo = $this->seo->for(
            $renderSite,
            $page,
            $urls,
            $asPreview,
            $asStaging,
            $content,
            blockIndexing: $blockIndexing || $editing,
        );
        $brand = $this->brandAssets->urls($site->content ?? []);
        $variants = SectionVariants::merge($content['variants'] ?? null, $site->designTemplate?->variants);
        $layoutVariant = $site->designTemplate?->layout_variant ?? SiteLayoutVariant::Standard;
        $layoutClass = trim($layoutVariant->bodyClass().' '.SectionVariants::bodyClasses($variants));

        $services = $this->pages->services($content);

        return view('sites.pages.'.$page['type'], [
            'site' => $renderSite,
            'lead' => $lead,
            'content' => $content,
            'page' => $page,
            'urls' => $urls,
            'seo' => $seo,
            'seoBuilder' => $this->seo,
            'brand' => $brand,
            'breadcrumbs' => $seo['breadcrumbs'],
            'nav' => $this->navigation($renderSite, $urls, $content),
            'services' => $services,
            'posts' => $page['type'] === 'blog' ? $this->pages->posts($site) : collect(),
            'sections' => $this->visibleSections($content, $photos, $hours, $mapEmbedUrl, $reviews->count(), count(SiteProofPoints::areas($lead)), $editing),
            'reviews' => $reviews,
            'reviewsStyle' => $reviewsStyle->value,
            'reviewsUrl' => $lead->reviewsUrl(),
            'onAccent' => $this->readableTextOn($content['accent']),
            'template' => $site->template->value,
            'layoutClass' => $layoutClass,
            'layoutVariants' => $variants,
            'locale' => $locale,
            'localeSwitcher' => count(SiteLocales::available($site->content)) > 1
                ? SiteLocales::switcher($site, $urls, $page['path'])
                : [],
            'asPreview' => $asPreview && ! $editor,
            'contactFormAction' => $asPreview ? route('sites.preview.contact', $site) : $urls->to('contact'),
            'contactFormUsesCsrf' => $asPreview,
            'editing' => $editor,
            'e' => new SiteEditing($content, $editor),
            'designCss' => SiteDesign::css($content),
            'sectionCss' => SiteSectionCss::stylesheet($content),
            'designFontsUrl' => SiteDesign::fontsUrl($content),
            'claimEmail' => $asPreview ? $site->team->owner()?->email : null,
            'proposalUrl' => $asPreview && ! $editor
                ? $site->team->proposals()
                    ->where('lead_id', $lead->id)
                    ->whereIn('status', [ProposalStatus::Sent, ProposalStatus::Accepted])
                    ->latest('id')
                    ->first()
                    ?->publicUrl()
                : null,
            'commentAction' => $asPreview ? PreviewLink::action('sites.preview.comments', $site) : null,
            'assistantEmbed' => $editor ? null : $this->assistantEmbed($site),
            'internalPreview' => request()->boolean('internal') || $editor,
            'sectionBeacons' => $asPreview && ! $editor && $site->exists && ! request()->boolean('internal')
                ? collect(PreviewSectionViewController::KEYS)
                    ->mapWithKeys(fn (string $key) => [$key => PreviewLink::action('sites.preview.sections', $site, ['section' => $key])])
                    ->all()
                : [],
            'logoUrl' => $lead->logoUrl(),
            ...$this->heroContext($content, $page, $photos, $services),
            'aboutPortrait' => $this->placedImage($content, 'about_portrait', $photos[1] ?? $photos[0] ?? null),
            'ctaBandImage' => $this->placedImage(
                $content,
                'cta_band',
                (($variants['cta'] ?? 'solid') === 'photo') ? ($photos[0] ?? null) : null,
            ),
            'galleryPhotos' => $photos,
            'hours' => $hours,
            'phoneHref' => $lead->phone ? 'tel:'.preg_replace('/[^\d+]/', '', $lead->phone) : null,
            'additionalPhones' => $lead->additional_phones ?? [],
            'socialLinks' => $lead->social_links ?? [],
            'mapEmbedUrl' => $mapEmbedUrl,
            'year' => now()->year,
            'processSteps' => SiteProofPoints::process($lead, $content),
            'whyPoints' => SiteProofPoints::why($lead, $content),
            'areaNames' => SiteProofPoints::areas($lead),
        ])->render();
    }

    /**
     * The script tag that adds the AI assistant, or null when the site's assistant is switched off.
     *
     * The tag always points at this app, so exported copies of the site keep talking to the same assistant.
     *
     * @return array{src: string, key: string}|null
     */
    protected function assistantEmbed(Site $site): ?array
    {
        $assistant = $site->assistant;

        if ($assistant === null || ! $assistant->enabled) {
            return null;
        }

        $path = public_path('widget/assistant.js');
        $version = is_file($path) ? filemtime($path) : 1;

        return [
            'src' => rtrim((string) config('app.url'), '/').'/widget/assistant.js?v='.$version,
            'key' => $assistant->public_key,
        ];
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

        foreach ($this->pages->customPages($content) as $custom) {
            if (! ($custom['in_menu'] ?? false)) {
                continue;
            }

            $prefix = ($custom['kind'] ?? '') === 'landing' ? 'landing' : 'locations';
            $slug = (string) ($custom['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $items[] = [
                'key' => $prefix.'/'.$slug,
                'label' => (string) ($custom['title'] ?? $slug),
                'url' => $urls->to($prefix.'/'.$slug),
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
    public function visibleSections(array $content, array $photos, array $hours, ?string $mapEmbedUrl, int $reviewCount = 0, int $areaCount = 0, bool $editing = false): array
    {
        $planned = array_values(array_filter(
            $content['sections'] ?? SiteSection::defaultOrder(),
            'is_string',
        ));
        $hidden = array_values(array_filter((array) ($content['hidden_sections'] ?? []), 'is_string'));

        if (
            $reviewCount >= Lead::MIN_TESTIMONIALS
            && ! in_array(SiteSection::Reviews->value, $planned, true)
            && ! in_array(SiteSection::Reviews->value, $hidden, true)
        ) {
            $planned = SiteStructuralIntent::insertSection($planned, SiteSection::Reviews->value);
        }

        if (
            $this->pages->isEnabled($content, 'gallery')
            && ! in_array(SiteSection::Gallery->value, $planned, true)
            && ! in_array(SiteSection::Gallery->value, $hidden, true)
        ) {
            $planned = SiteStructuralIntent::insertSection($planned, SiteSection::Gallery->value);
        }

        $visible = [];

        foreach ($planned as $value) {
            if (CustomHomeSection::isKey($value) && CustomHomeSection::payload($content, $value) !== null) {
                $visible[] = $value;

                continue;
            }

            $section = SiteSection::tryFrom($value);

            if ($section === null) {
                continue;
            }

            if ($this->sectionIsAvailable($section, $content, $photos, $hours, $mapEmbedUrl, $reviewCount, $areaCount, $planned, $editing)) {
                $visible[] = $section->value;
            }
        }

        return array_values(array_unique($visible));
    }

    /**
     * @param  list<string>  $planned
     */
    protected function sectionIsAvailable(
        SiteSection $section,
        array $content,
        array $photos,
        array $hours,
        ?string $mapEmbedUrl,
        int $reviewCount,
        int $areaCount,
        array $planned,
        bool $editing = false,
    ): bool {
        return match ($section) {
            SiteSection::Services => ! empty($content['services']),
            SiteSection::About => filled($content['about'] ?? null),
            SiteSection::Process => true,
            SiteSection::Why => true,
            SiteSection::Reviews => $reviewCount >= Lead::MIN_TESTIMONIALS,
            SiteSection::Gallery => $this->pages->isEnabled($content, 'gallery')
                ? ($editing || count($photos) >= 1)
                : count($photos) >= self::MIN_GALLERY_PHOTOS,
            SiteSection::Areas => $areaCount > 0,
            SiteSection::Hours => $hours !== [] || $mapEmbedUrl !== null,
            SiteSection::Faq => ! empty($content['faqs']),
            SiteSection::Contact => false,
        };
    }

    /**
     * Section keys the AI design editor may target on the homepage (chrome + visible blocks).
     *
     * @return list<string>
     */
    public function designTargetKeys(Site $site): array
    {
        $site->loadMissing('lead');

        $content = SiteLocales::merge($site->content, SiteLocales::default($site->content));
        $lead = $site->lead;
        $photos = $lead->photoUrls();
        $hours = $this->orderedHours($lead->opening_hours);
        $mapEmbedUrl = $this->mapEmbedUrl($lead->name, $lead->address);
        $reviews = $lead->testimonials();

        $keys = ['header', 'hero'];

        foreach ($this->visibleSections(
            $content,
            $photos,
            $hours,
            $mapEmbedUrl,
            $reviews->count(),
            count(SiteProofPoints::areas($lead)),
        ) as $section) {
            $keys[] = $section;
        }

        $keys[] = 'footer';

        return array_values(array_unique(array_filter(
            $keys,
            fn (string $key) => isset(SiteSectionCss::TARGETS[$key]),
        )));
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
     * Hero image and editor slot for this page. Service detail pages reuse that service's card photo.
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $page
     * @param  list<string>  $photos
     * @param  list<array{title: string, description: string, body: string, slug: string, image_url: string}>  $services
     * @return array{heroImage: ?string, heroVideo: ?string, heroSlot: string, heroIndex: ?int}
     */
    protected function heroContext(array $content, array $page, array $photos, array $services): array
    {
        if (($page['type'] ?? '') === 'service') {
            $slug = $page['service']['slug'] ?? null;
            $index = collect($services)->search(fn (array $service) => $service['slug'] === $slug);
            $index = $index === false ? null : (int) $index;
            $serviceUrl = is_string($page['service']['image_url'] ?? null) ? $page['service']['image_url'] : null;

            if (filled($serviceUrl) && ! str_starts_with($serviceUrl, 'data:')) {
                return [
                    'heroImage' => $serviceUrl,
                    'heroVideo' => null,
                    'heroSlot' => 'service',
                    'heroIndex' => $index,
                ];
            }

            return [
                'heroImage' => $this->placedImage($content, 'service_hero', $photos[0] ?? null),
                'heroVideo' => null,
                'heroSlot' => $index !== null ? 'service' : 'service_hero',
                'heroIndex' => $index,
            ];
        }

        $slot = SiteImagePlacements::forPageType((string) ($page['type'] ?? 'home'));
        $stored = is_string($content['images'][$slot] ?? null) ? $content['images'][$slot] : null;

        if (($page['type'] ?? '') === 'home' && SiteImagePlacements::allowsVideo($slot) && SiteImagePlacements::isVideoPath($stored)) {
            return [
                'heroImage' => null,
                'heroVideo' => app(SiteImageStorage::class)->url($stored),
                'heroSlot' => $slot,
                'heroIndex' => null,
            ];
        }

        return [
            'heroImage' => $this->placedImage($content, $slot, $photos[0] ?? null),
            'heroVideo' => null,
            'heroSlot' => $slot,
            'heroIndex' => null,
        ];
    }

    /**
     * A page-specific photo, or the shared lead photo until that page has its own.
     */
    protected function placedImage(array $content, string $slot, ?string $fallback): ?string
    {
        $path = $content['images'][$slot] ?? null;

        if (! is_string($path) || $path === '') {
            return $fallback;
        }

        return app(SiteImageStorage::class)->url($path) ?? $fallback;
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

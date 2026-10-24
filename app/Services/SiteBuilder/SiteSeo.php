<?php

namespace App\Services\SiteBuilder;

use App\Models\Lead;
use App\Models\Site;
use App\Models\SitePost;
use Illuminate\Support\Str;

/**
 * Builds the search-engine and social metadata for each page of a website.
 *
 * @phpstan-import-type ResolvedPage from SitePages
 *
 * @phpstan-type Seo array{
 *     title: string,
 *     description: string,
 *     canonical: string|null,
 *     robots: string,
 *     image: string|null,
 *     type: string,
 *     breadcrumbs: list<array{name: string, path: string, url: string}>,
 *     schema: list<array<string, mixed>>,
 * }
 */
class SiteSeo
{
    public const TITLE_MAX = 65;

    public const DESCRIPTION_MAX = 155;

    public function __construct(protected SitePages $pages) {}

    /**
     * Build the metadata for one page.
     *
     * @param  ResolvedPage  $page
     * @return Seo
     */
    public function for(Site $site, array $page, SiteUrls $urls, bool $asPreview, bool $asStaging = false, ?array $content = null): array
    {
        $lead = $site->lead;
        $content ??= $site->content;
        $image = $lead->photoUrls()[0] ?? $lead->logoUrl();

        $breadcrumbs = $this->breadcrumbs($page, $urls);
        $schema = [$this->business($lead, $urls, $image)];

        if (count($breadcrumbs) > 1) {
            $schema[] = $this->breadcrumbList($breadcrumbs, $urls);
        }

        if ($page['type'] === 'home' && ! empty($content['faqs'])) {
            $schema[] = $this->faqPage($content['faqs']);
        }

        if ($page['type'] === 'service') {
            $schema[] = $this->service($page['service'], $lead, $urls);
        }

        if ($page['type'] === 'post') {
            $schema[] = $this->blogPosting($page['post'], $lead, $urls, $image);
        }

        return [
            'title' => $this->title($site, $page, $content),
            'description' => $this->description($site, $page, $content),
            'canonical' => $asPreview ? null : $urls->absolute($page['path']),
            'robots' => ($asPreview || $asStaging) ? 'noindex, nofollow' : 'index, follow, max-image-preview:large',
            'image' => $image,
            'type' => $page['type'] === 'post' ? 'article' : 'website',
            'breadcrumbs' => $breadcrumbs,
            'schema' => $schema,
            'alternates' => $asPreview ? [] : $this->alternates($site, $page['path'], $urls),
        ];
    }

    /**
     * The trail shown above the page heading and in search results.
     *
     * @param  ResolvedPage  $page
     * @return list<array{name: string, path: string, url: string}>
     */
    public function breadcrumbs(array $page, SiteUrls $urls): array
    {
        $trail = [['name' => 'Home', 'path' => '', 'url' => $urls->to()]];

        if ($page['type'] === 'home') {
            return $trail;
        }

        if (in_array($page['type'], ['landing', 'location'], true)) {
            $trail[] = ['name' => $page['custom']['title'], 'path' => $page['path'], 'url' => $urls->to($page['path'])];

            return $trail;
        }

        $trail[] = ['name' => SitePages::PAGE_LABELS[$page['key']], 'path' => $page['key'], 'url' => $urls->to($page['key'])];

        if ($page['type'] === 'service') {
            $trail[] = ['name' => $page['service']['title'], 'path' => $page['path'], 'url' => $urls->to($page['path'])];
        }

        if ($page['type'] === 'post') {
            $trail[] = ['name' => $page['post']->title, 'path' => $page['path'], 'url' => $urls->to($page['path'])];
        }

        return $trail;
    }

    /**
     * Build the title shown in search results and browser tabs.
     *
     * @param  ResolvedPage  $page
     */
    public function title(Site $site, array $page, ?array $content = null): string
    {
        $lead = $site->lead;
        $name = $lead->name;
        $inCity = $lead->city ? ' in '.$lead->city : '';
        $category = $lead->category ?: 'Local business';

        $title = match ($page['type']) {
            'home' => $lead->city ? "{$name} | {$category}{$inCity}" : $name,
            'about' => "About {$name} | {$category}{$inCity}",
            'services' => "{$category} Services{$inCity} | {$name}",
            'service' => "{$page['service']['title']}{$inCity} | {$name}",
            'gallery' => "Photo Gallery | {$name}",
            'blog' => "Blog | {$name}",
            'post' => "{$page['post']->title} | {$name}",
            'contact' => "Contact {$name} | {$category}{$inCity}",
            'landing', 'location' => "{$page['custom']['headline']} | {$name}",
        };

        return Str::limit($title, self::TITLE_MAX, '…');
    }

    /**
     * Build the snippet shown under the title in search results.
     *
     * @param  ResolvedPage  $page
     */
    public function description(Site $site, array $page, ?array $content = null): string
    {
        $lead = $site->lead;
        $content ??= $site->content;
        $name = $lead->name;
        $inCity = $lead->city ? ' in '.$lead->city : '';

        $text = match ($page['type']) {
            'home' => $content['tagline'].' '.$content['about'],
            'about' => $content['about'],
            'services' => "Explore the services {$name} offers{$inCity}: ".collect($this->pages->services($content))->pluck('title')->take(5)->implode(', ').'.',
            'service' => $page['service']['description'].' Get in touch with '.$name.' today.',
            'gallery' => "Photos of the work and the team at {$name}{$inCity}.",
            'blog' => "News, advice and updates from {$name}{$inCity}.",
            'post' => $this->postSummary($page['post']),
            'contact' => "Get in touch with {$name}{$inCity}. ".($lead->phone ? "Call {$lead->phone} or send us a message." : 'Send us a message and we will get back to you.'),
            'landing', 'location' => ($page['custom']['intro'] ?? '').' '.$page['custom']['body'],
        };

        return Str::of($text)->squish()->limit(self::DESCRIPTION_MAX, '…')->toString();
    }

    /**
     * @return list<array{hreflang: string, href: string}>
     */
    protected function alternates(Site $site, string $pagePath, SiteUrls $urls): array
    {
        $alternates = [];

        foreach (SiteLocales::available($site->content) as $locale) {
            $alternates[] = [
                'hreflang' => $locale,
                'href' => $urls->forLocale($locale)->absolute($pagePath),
            ];
        }

        $default = SiteLocales::default($site->content);
        $alternates[] = [
            'hreflang' => 'x-default',
            'href' => $urls->forLocale($default)->absolute($pagePath),
        ];

        return $alternates;
    }

    /**
     * The summary used when a blog post has no excerpt of its own.
     */
    public function postSummary(SitePost $post): string
    {
        return filled($post->excerpt) ? $post->excerpt : Str::of($post->body)->squish()->limit(self::DESCRIPTION_MAX, '…')->toString();
    }

    /**
     * Encode structured data for a script tag without allowing it to break out.
     *
     * @param  array<string, mixed>  $data
     */
    public function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    /**
     * @return array<string, mixed>
     */
    protected function business(Lead $lead, SiteUrls $urls, ?string $image): array
    {
        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $lead->address,
            'addressLocality' => $lead->city,
            'addressRegion' => $lead->state,
            'addressCountry' => $lead->country,
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            '@id' => $urls->absolute().'#business',
            'name' => $lead->name,
            'url' => $urls->absolute(),
            'image' => $image,
            'telephone' => $lead->phone,
            'email' => $lead->email,
            'address' => count($address) > 1 ? $address : null,
            'geo' => $lead->latitude !== null && $lead->longitude !== null
                ? ['@type' => 'GeoCoordinates', 'latitude' => $lead->latitude, 'longitude' => $lead->longitude]
                : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  list<array{name: string, path: string, url: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    protected function breadcrumbList(array $breadcrumbs, SiteUrls $urls): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $urls->absolute($crumb['path']),
            ], $breadcrumbs, array_keys($breadcrumbs)),
        ];
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faqs
     * @return array<string, mixed>
     */
    protected function faqPage(array $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ], $faqs),
        ];
    }

    /**
     * @param  array{title: string, description: string, body: string, slug: string}  $service
     * @return array<string, mixed>
     */
    protected function service(array $service, Lead $lead, SiteUrls $urls): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $service['title'],
            'description' => $service['description'],
            'url' => $urls->absolute('services/'.$service['slug']),
            'provider' => ['@id' => $urls->absolute().'#business'],
            'areaServed' => $lead->city,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function blogPosting(SitePost $post, Lead $lead, SiteUrls $urls, ?string $image): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => Str::limit($post->title, 110, ''),
            'description' => $this->postSummary($post),
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'mainEntityOfPage' => $urls->absolute('blog/'.$post->slug),
            'image' => $image,
            'author' => ['@type' => 'Organization', 'name' => $lead->name],
            'publisher' => ['@id' => $urls->absolute().'#business'],
        ]);
    }
}

<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;
use App\Models\SitePost;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Works out which pages a website has and what lives at each address.
 *
 * @phpstan-type ServicePage array{title: string, description: string, body: string, slug: string}
 * @phpstan-type CustomPage array{kind: string, slug: string, title: string, headline: string, intro?: string, body: string, cta_label?: string, city?: string}
 * @phpstan-type ResolvedPage array{type: string, key: string, path: string, service?: ServicePage, post?: SitePost, custom?: CustomPage}
 */
class SitePages
{
    /**
     * The pages in navigation order, after the home page.
     *
     * @var list<string>
     */
    public const PAGES = ['about', 'services', 'gallery', 'blog', 'contact'];

    /**
     * The pages that can be switched off. The contact page is always there.
     *
     * @var list<string>
     */
    public const OPTIONAL = ['about', 'services', 'gallery', 'blog'];

    public const PAGE_LABELS = [
        'about' => 'About',
        'services' => 'Services',
        'gallery' => 'Gallery',
        'blog' => 'Blog',
        'contact' => 'Contact',
    ];

    /**
     * Whether the page has been switched on for the site.
     *
     * @param  array<string, mixed>  $content
     */
    public function isEnabled(array $content, string $page): bool
    {
        if ($page === 'contact') {
            return true;
        }

        return in_array($page, $content['pages'] ?? self::OPTIONAL, true);
    }

    /**
     * Get the pages that exist for the site, in navigation order.
     *
     * @return list<string>
     */
    public function available(Site $site): array
    {
        $content = $site->content;

        $exists = fn (string $page): bool => match ($page) {
            'about' => filled($content['about'] ?? null),
            'services' => ! empty($content['services']),
            'gallery' => count($site->lead->photoUrls()) >= SiteRenderer::MIN_GALLERY_PHOTOS,
            'blog' => $this->posts($site)->isNotEmpty(),
            'contact' => true,
        };

        return array_values(array_filter(
            self::PAGES,
            fn (string $page) => $this->isEnabled($content, $page) && $exists($page),
        ));
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<CustomPage>
     */
    public function customPages(array $content): array
    {
        return array_values(array_filter(
            $content['custom_pages'] ?? [],
            fn ($page) => is_array($page) && filled($page['slug'] ?? null) && filled($page['kind'] ?? null),
        ));
    }

    /**
     * Get the services with a unique, URL-safe slug each.
     *
     * @param  array<string, mixed>  $content
     * @return list<ServicePage>
     */
    public function services(array $content): array
    {
        $taken = [];
        $services = [];

        foreach ($content['services'] ?? [] as $service) {
            $slug = self::uniqueSlug($service['slug'] ?? '', $service['title'], $taken);
            $taken[] = $slug;

            $services[] = [
                'title' => $service['title'],
                'description' => $service['description'],
                'body' => (string) ($service['body'] ?? ''),
                'slug' => $slug,
            ];
        }

        return $services;
    }

    /**
     * Get the published blog posts, newest first.
     *
     * @return Collection<int, SitePost>
     */
    public function posts(Site $site): Collection
    {
        return $site->posts()->published()->latest('published_at')->get();
    }

    /**
     * Work out which page lives at the path, or null when there is none.
     *
     * @return ResolvedPage|null
     */
    public function resolve(Site $site, string $path): ?array
    {
        $path = trim($path, '/');

        if ($path === '') {
            return ['type' => 'home', 'key' => 'home', 'path' => ''];
        }

        $segments = explode('/', $path);
        $page = $segments[0];

        if ($page === 'landing' || $page === 'locations') {
            return $this->resolveCustom($site, $page, $segments);
        }

        if (! in_array($page, $this->available($site), true)) {
            return null;
        }

        if (count($segments) === 1) {
            return ['type' => $page, 'key' => $page, 'path' => $path];
        }

        if (count($segments) !== 2) {
            return null;
        }

        if ($page === 'services') {
            $service = collect($this->services($site->content))->firstWhere('slug', $segments[1]);

            return $service ? ['type' => 'service', 'key' => 'services', 'path' => $path, 'service' => $service] : null;
        }

        if ($page === 'blog') {
            $post = $this->posts($site)->firstWhere('slug', $segments[1]);

            return $post ? ['type' => 'post', 'key' => 'blog', 'path' => $path, 'post' => $post] : null;
        }

        return null;
    }

    /**
     * @param  list<string>  $segments
     * @return ResolvedPage|null
     */
    protected function resolveCustom(Site $site, string $prefix, array $segments): ?array
    {
        if (count($segments) !== 2) {
            return null;
        }

        $kind = $prefix === 'landing' ? 'landing' : 'location';
        $custom = collect($this->customPages($site->content))->first(
            fn (array $page) => $page['kind'] === $kind && $page['slug'] === $segments[1],
        );

        if ($custom === null) {
            return null;
        }

        return [
            'type' => $kind,
            'key' => $prefix,
            'path' => implode('/', $segments),
            'custom' => $custom,
        ];
    }

    /**
     * Get the path of every page the site has, home page first.
     *
     * @return list<string>
     */
    public function paths(Site $site): array
    {
        $available = $this->available($site);
        $paths = [''];

        foreach ($available as $page) {
            $paths[] = $page;

            if ($page === 'services') {
                foreach ($this->services($site->content) as $service) {
                    $paths[] = 'services/'.$service['slug'];
                }
            }

            if ($page === 'blog') {
                foreach ($this->posts($site) as $post) {
                    $paths[] = 'blog/'.$post->slug;
                }
            }
        }

        foreach ($this->customPages($site->content) as $page) {
            $paths[] = ($page['kind'] === 'landing' ? 'landing' : 'locations').'/'.$page['slug'];
        }

        return $paths;
    }

    /**
     * Turn a preferred slug (or the title) into one that no other page is using.
     *
     * @param  list<string>  $taken
     */
    public static function uniqueSlug(?string $preferred, string $title, array $taken): string
    {
        $base = Str::limit(Str::slug((string) $preferred) ?: Str::slug($title) ?: 'page', 60, '');
        $slug = $base;
        $counter = 2;

        while (in_array($slug, $taken, true)) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}

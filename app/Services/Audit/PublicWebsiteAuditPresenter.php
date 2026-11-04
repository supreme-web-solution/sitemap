<?php

namespace App\Services\Audit;

class PublicWebsiteAuditPresenter
{
    /**
     * @param  array{key: string, label: string, status: string, detail: string}  $check
     * @return array{title: string, summary: string, recommendation: string|null, status: string}
     */
    public function present(array $check): array
    {
        $key = $check['key'];
        $status = $check['status'];

        $copy = $this->copy()[$key] ?? [
            'title' => $check['label'],
            'summary' => $check['detail'],
            'recommendation' => null,
        ];

        $summary = $status === 'pass'
            ? ($copy['pass'] ?? $copy['summary'])
            : ($check['detail'] ?: $copy['summary']);

        return [
            'title' => $copy['title'],
            'summary' => $summary,
            'recommendation' => $status === 'pass' ? null : ($copy['recommendation'] ?? null),
            'status' => $status,
        ];
    }

    /**
     * @return array<string, array{title: string, summary: string, pass?: string, recommendation: string|null}>
     */
    protected function copy(): array
    {
        return [
            'https' => [
                'title' => 'Secure connection',
                'summary' => 'Customers expect the padlock icon in the browser.',
                'pass' => 'Your site uses HTTPS, so visitors are not warned that the connection is unsafe.',
                'recommendation' => 'Switch the site to HTTPS so customers trust the connection.',
            ],
            'viewport' => [
                'title' => 'Mobile phones',
                'summary' => 'Most visitors will open your site on a phone.',
                'pass' => 'The site is set up to resize correctly on small screens.',
                'recommendation' => 'Add a mobile-friendly layout so text and buttons are easy to use on phones.',
            ],
            'title' => [
                'title' => 'Page title',
                'summary' => 'This is what people see in Google and in browser tabs.',
                'pass' => 'The title is a sensible length for search results.',
                'recommendation' => 'Use one clear title (about 10–70 characters) with your business name and what you do.',
            ],
            'description' => [
                'title' => 'Google description',
                'summary' => 'The short blurb under your link in search results.',
                'pass' => 'The description helps people understand your business before they click.',
                'recommendation' => 'Write a unique description (about 50–170 characters) that explains who you help.',
            ],
            'heading' => [
                'title' => 'Main headline',
                'summary' => 'The first heading should tell visitors where they are.',
                'pass' => 'There is a single clear main heading on the page.',
                'recommendation' => 'Use one main headline (H1) that states your business and location or service.',
            ],
            'contact' => [
                'title' => 'Easy contact',
                'summary' => 'Can someone tap to call or email in one step?',
                'pass' => 'Phone or email links are available for customers.',
                'recommendation' => 'Add a tap-to-call phone link and/or a mailto email link near the top.',
            ],
            'call_to_action' => [
                'title' => 'Clear next step',
                'summary' => 'Visitors should know what to do next (book, call, quote, etc.).',
                'pass' => 'The page invites visitors to take a clear action.',
                'recommendation' => 'Add an obvious button such as “Call now”, “Book online”, or “Get a quote”.',
            ],
            'images' => [
                'title' => 'Photos for everyone',
                'summary' => 'Text alternatives help search engines and visually impaired visitors.',
                'pass' => 'Images include descriptions where needed.',
                'recommendation' => 'Add short alt text to photos, especially logos and service images.',
            ],
            'speed' => [
                'title' => 'Load time',
                'summary' => 'Slow pages lose customers before they read anything.',
                'pass' => 'The server responded quickly when we tested the page.',
                'recommendation' => 'Ask your web host or developer to improve speed (caching, smaller images, less bloat).',
            ],
            'weight' => [
                'title' => 'Page weight',
                'summary' => 'Very heavy pages load slowly on mobile data.',
                'pass' => 'The page size is reasonable.',
                'recommendation' => 'Reduce unnecessary scripts and compress images to speed up loading.',
            ],
            'social' => [
                'title' => 'Link previews',
                'summary' => 'When someone shares your site, does the preview look good?',
                'pass' => 'Social sharing tags are in place.',
                'recommendation' => 'Add Open Graph tags so shared links show a title and image.',
            ],
        ];
    }
}

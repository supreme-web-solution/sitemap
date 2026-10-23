<?php

namespace App\Services\Audit;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

class WebsiteAuditor
{
    /**
     * Relative importance of each check when computing the 0-100 score.
     *
     * @var array<string, int>
     */
    protected const WEIGHTS = [
        'https' => 2,
        'viewport' => 3,
        'title' => 2,
        'description' => 2,
        'heading' => 1,
        'contact' => 3,
        'call_to_action' => 2,
        'images' => 1,
        'speed' => 2,
        'weight' => 1,
        'social' => 1,
    ];

    public function __construct(protected SafeHttpFetcher $fetcher) {}

    /**
     * Audit a website's public home page.
     *
     * @return array{url: string, final_url: string|null, fetched_at: string, status_code: int|null, score: int, error: string|null, checks: list<array{key: string, label: string, status: string, detail: string}>}
     */
    public function audit(string $website): array
    {
        $url = $this->normalize($website);

        $result = [
            'url' => $url,
            'final_url' => null,
            'fetched_at' => now()->toISOString(),
            'status_code' => null,
            'score' => 0,
            'error' => null,
            'checks' => [],
        ];

        try {
            $page = $this->fetcher->fetch($url);
        } catch (UnsafeUrlException $exception) {
            return [...$result, 'error' => $exception->getMessage()];
        } catch (ConnectionException) {
            return [...$result, 'error' => 'The website could not be reached.'];
        }

        $result['final_url'] = $page['url'];
        $result['status_code'] = $page['status'];

        if ($page['status'] < 200 || $page['status'] >= 300) {
            return [...$result, 'error' => "The website responded with HTTP {$page['status']}."];
        }

        $checks = $this->runChecks($page);

        return [
            ...$result,
            'checks' => $checks,
            'score' => $this->score($checks),
        ];
    }

    /**
     * Add a scheme to bare domains.
     */
    public function normalize(string $website): string
    {
        $website = trim($website);

        return preg_match('#^https?://#i', $website) === 1 ? $website : 'https://'.$website;
    }

    /**
     * @param  array{url: string, status: int, body: string, bytes: int, elapsed_ms: int, truncated: bool}  $page
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    protected function runChecks(array $page): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$page['body']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        $text = fn (string $query): string => trim((string) $xpath->evaluate("string({$query})"));
        $count = fn (string $query): int => (int) $xpath->evaluate("count({$query})");

        $title = $text('//title');
        $description = $text('//meta[translate(@name,"DESCRIPTION","description")="description"]/@content');

        $images = $count('//img');
        $imagesWithoutAlt = $count('//img[not(@alt) or normalize-space(@alt)=""]');

        $headings = $count('//h1');

        $hasContact = $count('//a[starts-with(translate(@href,"TELMAIO","telmaio"),"tel:") or starts-with(translate(@href,"MAILTO","mailto"),"mailto:")]') > 0;

        $buttons = collect($xpath->query('//a|//button') ?: [])
            ->map(fn ($node) => Str::lower(trim($node->textContent)))
            ->contains(fn (string $label) => preg_match('/\b(book|call|contact|quote|get started|order|reserve|appointment|enquire|inquire|schedule)\b/', $label) === 1);

        return [
            $this->check('https', 'Secure connection (HTTPS)', str_starts_with($page['url'], 'https://'), 'Served over HTTPS.', 'Not served over HTTPS, browsers show a warning.'),
            $this->check('viewport', 'Mobile-friendly layout', $count('//meta[@name="viewport"]') > 0, 'Has a mobile viewport tag.', 'No mobile viewport tag, likely poor on phones.'),
            match (true) {
                $title === '' => $this->result('title', 'Page title', 'fail', 'The page has no title.'),
                mb_strlen($title) < 10 || mb_strlen($title) > 70 => $this->result('title', 'Page title', 'warn', 'The title is '.mb_strlen($title).' characters, aim for 10-70.'),
                default => $this->result('title', 'Page title', 'pass', 'Has a well-sized title.'),
            },
            match (true) {
                $description === '' => $this->result('description', 'Search description', 'fail', 'No meta description for search results.'),
                mb_strlen($description) < 50 || mb_strlen($description) > 170 => $this->result('description', 'Search description', 'warn', 'The description is '.mb_strlen($description).' characters, aim for 50-170.'),
                default => $this->result('description', 'Search description', 'pass', 'Has a well-sized description.'),
            },
            match (true) {
                $headings === 0 => $this->result('heading', 'Main heading', 'fail', 'No main heading (h1).'),
                $headings > 1 => $this->result('heading', 'Main heading', 'warn', "{$headings} main headings, there should be one."),
                default => $this->result('heading', 'Main heading', 'pass', 'Has one main heading.'),
            },
            $this->check('contact', 'Click-to-call or email', $hasContact, 'Has a phone or email link.', 'No tap-to-call or email link for customers.', failStatus: 'fail'),
            $this->check('call_to_action', 'Call to action', $buttons, 'Has a clear action such as book or call.', 'No obvious call to action.', failStatus: 'warn'),
            match (true) {
                $images === 0 || $imagesWithoutAlt === 0 => $this->result('images', 'Image descriptions', 'pass', 'Images have descriptions.'),
                $imagesWithoutAlt / $images <= 0.25 => $this->result('images', 'Image descriptions', 'warn', "{$imagesWithoutAlt} of {$images} images have no alt text."),
                default => $this->result('images', 'Image descriptions', 'fail', "{$imagesWithoutAlt} of {$images} images have no alt text."),
            },
            match (true) {
                $page['elapsed_ms'] < 1500 => $this->result('speed', 'Server response', 'pass', "Responded in {$page['elapsed_ms']} ms."),
                $page['elapsed_ms'] < 3500 => $this->result('speed', 'Server response', 'warn', "Responded in {$page['elapsed_ms']} ms."),
                default => $this->result('speed', 'Server response', 'fail', "Responded in {$page['elapsed_ms']} ms, which is slow."),
            },
            match (true) {
                $page['truncated'] || $page['bytes'] > 1_000_000 => $this->result('weight', 'Page size', 'warn', 'The page HTML is very large.'),
                default => $this->result('weight', 'Page size', 'pass', 'Page HTML is a reasonable size.'),
            },
            $this->check('social', 'Social sharing tags', $count('//meta[@property="og:title"]') > 0, 'Has Open Graph tags.', 'No Open Graph tags, links look plain when shared.', failStatus: 'warn'),
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function check(string $key, string $label, bool $passed, string $passDetail, string $failDetail, string $failStatus = 'fail'): array
    {
        return $this->result($key, $label, $passed ? 'pass' : $failStatus, $passed ? $passDetail : $failDetail);
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function result(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }

    /**
     * @param  list<array{key: string, status: string}>  $checks
     */
    protected function score(array $checks): int
    {
        $earned = 0.0;
        $possible = 0;

        foreach ($checks as $check) {
            $weight = self::WEIGHTS[$check['key']] ?? 1;
            $possible += $weight;
            $earned += $weight * match ($check['status']) {
                'pass' => 1.0,
                'warn' => 0.5,
                default => 0.0,
            };
        }

        return $possible === 0 ? 0 : (int) round($earned / $possible * 100);
    }
}

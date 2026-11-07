<?php

namespace App\Services\SiteBuilder;

use App\Enums\SiteSection;
use App\Models\Lead;
use App\Models\Site;
use Illuminate\Support\Str;

class SiteQualityChecker
{
    public function __construct(
        protected SitePages $pages,
        protected SiteSeo $seo,
        protected SiteRenderer $renderer,
    ) {}

    /**
     * Phrases a business must confirm before a site may claim them.
     *
     * @var list<string>
     */
    protected const RISKY_CLAIMS = [
        '24/7', '24 hours', '24-hour', 'licensed', 'insured', 'certified', 'guarantee',
        'award-winning', 'award winning', 'family-owned', 'family owned', 'years of experience',
        'free quote', 'free estimate', 'same-day', 'same day', 'emergency',
    ];

    /**
     * Check a generated website before it is shared with the prospect.
     *
     * @return array{checks: list<array{key: string, label: string, status: string, detail: string, fixable: bool}>, passed: int, total: int}
     */
    public function check(Site $site): array
    {
        $site->loadMissing('lead');

        $lead = $site->lead;
        $content = $site->content;
        $sections = $content['sections'] ?? SiteSection::defaultOrder();

        $checks = [
            $this->result(
                'headline',
                'Headline',
                filled($content['headline'] ?? null) && mb_strlen($content['headline']) <= 90 ? 'pass' : 'warn',
                filled($content['headline'] ?? null) ? 'Headline is set and a good length.' : 'The headline is missing.',
            ),
            $this->result(
                'contact',
                'Way to contact the business',
                filled($lead->phone) || filled($lead->email) ? 'pass' : 'fail',
                filled($lead->phone) || filled($lead->email)
                    ? 'Visitors can call or email.'
                    : 'No phone or email, visitors cannot get in touch.',
            ),
            $this->result(
                'services',
                'Services',
                ! in_array('services', $sections, true) || ! empty($content['services']) ? 'pass' : 'warn',
                ! empty($content['services']) ? count($content['services']).' services listed.' : 'The services section is empty.',
            ),
            $this->result(
                'hours',
                'Opening hours',
                ! in_array('hours', $sections, true) || ! empty($lead->opening_hours) ? 'pass' : 'warn',
                ! empty($lead->opening_hours) ? 'Hours are available.' : 'The hours section has no verified hours.',
            ),
            $this->result(
                'gallery',
                'Gallery photos',
                ! in_array('gallery', $sections, true) || $lead->photoUrls() !== [] ? 'pass' : 'warn',
                $lead->photoUrls() !== [] ? 'Photos are available.' : 'The gallery has no photos.',
            ),
            $this->reviews($site),
            $this->unverifiedClaims($site),
            ...$this->performance($site),
            ...$this->seoChecks($site),
        ];

        $passed = count(array_filter($checks, fn (array $check) => $check['status'] === 'pass'));

        return ['checks' => $checks, 'passed' => $passed, 'total' => count($checks)];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string, fixable: bool}
     */
    protected function reviews(Site $site): array
    {
        $lead = $site->lead;
        $sections = $site->content['sections'] ?? SiteSection::defaultOrder();
        $visible = $lead->reviews()->where('is_visible', true)->count();

        if ($visible >= Lead::MIN_TESTIMONIALS && in_array(SiteSection::Reviews->value, $sections, true)) {
            return $this->result('reviews', 'Customer reviews', 'pass', "{$visible} Google reviews are shown on the site.");
        }

        if ($visible >= Lead::MIN_TESTIMONIALS) {
            return $this->result('reviews', 'Customer reviews', 'warn', "{$visible} reviews are ready, but the Customer reviews section is switched off.");
        }

        if ($lead->reviews_count < Lead::MIN_TESTIMONIALS) {
            return $this->result('reviews', 'Customer reviews', 'pass', 'This business has too few Google reviews to show a testimonials section.');
        }

        return $this->result('reviews', 'Customer reviews', 'warn', 'The business has Google reviews but fewer than '.Lead::MIN_TESTIMONIALS.' are available to show. Fetch them from the reviews panel.');
    }

    /**
     * Measure the homepage we generate: how long it takes to build and how large the HTML is.
     *
     * @return list<array{key: string, label: string, status: string, detail: string, fixable: bool}>
     */
    protected function performance(Site $site): array
    {
        $started = hrtime(true);
        $html = $this->renderer->render($site) ?? '';
        $elapsed = (int) ((hrtime(true) - $started) / 1_000_000);
        $bytes = strlen($html);
        $kilobytes = max(1, (int) round($bytes / 1024));

        return [
            match (true) {
                $html === '' => $this->result('speed', 'Homepage speed', 'warn', 'The homepage could not be measured.'),
                $elapsed < 1500 => $this->result('speed', 'Homepage speed', 'pass', "The homepage builds in {$elapsed} ms."),
                default => $this->result('speed', 'Homepage speed', 'warn', "The homepage builds in {$elapsed} ms, which is slow."),
            },
            match (true) {
                $bytes > 1_000_000 => $this->result('weight', 'Homepage size', 'warn', "The homepage HTML is {$kilobytes} KB, which is heavy."),
                default => $this->result('weight', 'Homepage size', 'pass', "The homepage HTML is {$kilobytes} KB."),
            },
        ];
    }

    /**
     * Check the search-engine basics for the home page and the service pages.
     *
     * @return list<array{key: string, label: string, status: string, detail: string, fixable: bool}>
     */
    protected function seoChecks(Site $site): array
    {
        $lead = $site->lead;
        $home = $this->pages->resolve($site, '');
        $description = $this->seo->description($site, $home);
        $services = $this->pages->services($site->content);
        $thin = count(array_filter($services, fn (array $service) => mb_strlen($service['body']) < 200));
        $hasServicePages = $this->pages->isEnabled($site->content, 'services') && $services !== [];

        return [
            $this->result(
                'seo-snippet',
                'Search result snippet',
                mb_strlen($description) >= 70 ? 'pass' : 'warn',
                mb_strlen($description) >= 70
                    ? 'The page title and description are a good length for Google.'
                    : 'The description is short. Expand the tagline or the About text.',
            ),
            $this->result(
                'seo-local',
                'Local search details',
                filled($lead->city) && filled($lead->address) ? 'pass' : 'warn',
                filled($lead->city) && filled($lead->address)
                    ? 'Business name, city and address are included in the page data.'
                    : 'Add the city and address so the site can show up in local searches.',
            ),
            $this->result(
                'seo-service-pages',
                'Service pages',
                ! $hasServicePages || $thin === 0 ? 'pass' : 'warn',
                ! $hasServicePages
                    ? 'Service pages are switched off.'
                    : ($thin === 0
                        ? 'Every service page has its own detailed copy.'
                        : $thin.' of '.count($services).' service pages have little unique text. Add details to each one so it can rank on its own.'),
            ),
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string, fixable: bool}
     */
    protected function unverifiedClaims(Site $site): array
    {
        $content = $site->content;

        $generated = Str::lower(implode(' ', array_filter([
            $content['headline'] ?? '',
            $content['tagline'] ?? '',
            $content['about'] ?? '',
            ...array_map(fn (array $service) => ($service['title'] ?? '').' '.($service['description'] ?? '').' '.($service['body'] ?? ''), $content['services'] ?? []),
            ...array_map(fn (array $faq) => ($faq['question'] ?? '').' '.($faq['answer'] ?? ''), $content['faqs'] ?? []),
        ])));

        $known = Str::lower(($site->lead->description ?? '').' '.($site->lead->notes ?? ''));

        $unconfirmed = collect(self::RISKY_CLAIMS)
            ->filter(fn (string $claim) => str_contains($generated, $claim) && ! str_contains($known, $claim))
            ->values();

        return $this->result(
            'claims',
            'Unverified claims',
            $unconfirmed->isEmpty() ? 'pass' : 'warn',
            $unconfirmed->isEmpty()
                ? 'No risky claims found.'
                : 'Confirm with the owner before sharing: '.$unconfirmed->implode(', ').'.',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string, fixable: bool}
     */
    protected function result(string $key, string $label, string $status, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
            'fixable' => self::isAiFixable($key),
        ];
    }

    /**
     * Whether Fix with AI can attempt this finding (any check that has an instruction).
     */
    public static function isAiFixable(string $key): bool
    {
        return self::aiInstruction($key) !== null;
    }

    /**
     * @return list<string>
     */
    public static function fixableKeys(): array
    {
        return [
            'headline', 'contact', 'services', 'hours', 'gallery', 'reviews', 'claims',
            'speed', 'weight', 'seo-snippet', 'seo-local', 'seo-service-pages',
            'alt', 'lang', 'viewport', 'image-weight',
        ];
    }

    /**
     * True when this chat instruction came from a quality Fix with AI action.
     */
    public static function isQualityFixInstruction(string $instruction): bool
    {
        $trimmed = trim($instruction);

        if ($trimmed === '') {
            return false;
        }

        foreach (self::fixableKeys() as $key) {
            if (self::aiInstruction($key) === $trimmed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Instruction sent to the existing AI editor for a quality finding.
     */
    public static function aiInstruction(string $key): ?string
    {
        return match ($key) {
            'headline' => 'Apply this quality fix now using Site facts only. Shorten the headline to at most 90 characters while keeping the business name and city. Do not invent claims. Do not ask the user questions.',
            'contact' => 'Apply this quality fix now using Site facts only. If a phone or email is listed in Site facts, make sure the contact section and CTA use it. If both are missing, rewrite the contact section so visitors can still reach out without inventing a phone number or email. Do not ask the user questions.',
            'services' => 'Apply this quality fix now using Site facts only. Write three specific services from details already on the site. Do not invent licenses or guarantees. Do not ask the user questions.',
            'hours' => 'Apply this quality fix now. Do not invent opening hours. If hours are missing, hide the hours section. Do not ask the user questions.',
            'gallery' => 'Apply this quality fix now. Do not invent photos. If there are no photos, hide the gallery section. Do not ask the user questions.',
            'reviews' => 'Apply this quality fix now. If customer reviews exist, keep the Customer reviews section on. If they do not, switch it off. Do not ask the user questions.',
            'claims' => 'Apply this quality fix now. Remove unverified claims such as licensed, insured, 24/7, award-winning, or family-owned unless they already appear in the business notes. Do not ask the user questions.',
            'speed' => 'Apply this quality fix now using Site facts only. Simplify the homepage so it builds faster: prefer a simpler hero layout, keep about and services concise, and avoid heavy custom CSS. Do not invent business facts. Do not ask the user questions.',
            'weight' => 'Apply this quality fix now using Site facts only. Reduce homepage HTML weight: shorten long copy blocks, simplify layouts, and avoid large embedded media in the copy. Do not invent business facts. Do not ask the user questions.',
            'seo-snippet' => 'Apply this quality fix now using Site facts only. Lengthen the tagline and about text so a search snippet can be at least 70 characters, without inventing facts. Do not ask the user questions.',
            'seo-local' => 'Apply this quality fix now using Site facts only. Put the known city and address from Site facts into the headline and about text. If city or address is marked missing in Site facts, still improve local wording with whatever city is known and do not invent a street address. Do not ask the user questions.',
            'seo-service-pages' => 'Apply this quality fix now using Site facts only. Expand every service page body to at least 200 characters. Use the business name, city, and each service title already on the site. Rephrase and add practical local detail from known facts. Do not invent licenses, certifications, years in business, or guarantees. Do not ask the user questions.',
            'alt' => 'Apply this quality fix now. Write short, factual captions in the about or gallery copy that describe the photos. Do not invent what the photos show. Do not ask the user questions.',
            'lang' => 'Apply this quality fix now. Do not change facts. Keep the copy in clear English. Do not ask the user questions.',
            'viewport' => 'Apply this quality fix now. Keep the layout simple so it works on a phone. Do not add new claims. Do not ask the user questions.',
            'image-weight' => 'Apply this quality fix now. Do not embed large images in the copy. Refer to existing photos only. Do not ask the user questions.',
            default => null,
        };
    }
}

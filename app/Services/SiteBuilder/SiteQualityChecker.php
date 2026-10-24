<?php

namespace App\Services\SiteBuilder;

use App\Enums\SiteSection;
use App\Models\Lead;
use App\Models\Site;
use Illuminate\Support\Str;

class SiteQualityChecker
{
    public function __construct(protected SitePages $pages, protected SiteSeo $seo) {}

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
     * @return array{checks: list<array{key: string, label: string, status: string, detail: string}>, passed: int, total: int}
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
            ...$this->seoChecks($site),
        ];

        $passed = count(array_filter($checks, fn (array $check) => $check['status'] === 'pass'));

        return ['checks' => $checks, 'passed' => $passed, 'total' => count($checks)];
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
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
     * Check the search-engine basics for the home page and the service pages.
     *
     * @return list<array{key: string, label: string, status: string, detail: string}>
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
     * @return array{key: string, label: string, status: string, detail: string}
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
     * @return array{key: string, label: string, status: string, detail: string}
     */
    protected function result(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }

    /**
     * Instruction sent to the existing AI editor for a quality finding.
     */
    public static function aiInstruction(string $key): ?string
    {
        return match ($key) {
            'headline' => 'Shorten the headline to at most 90 characters while keeping the business name and city. Do not invent claims.',
            'contact' => 'Do not invent a phone number or email. If contact details are missing, make the contact section ask the visitor to reach out without fake numbers.',
            'services' => 'Write three specific services using only details already on the site. Do not invent licenses or guarantees.',
            'hours' => 'Do not invent opening hours. If hours are missing, hide the hours section.',
            'gallery' => 'Do not invent photos. If there are no photos, hide the gallery section.',
            'reviews' => 'If customer reviews exist, keep the Customer reviews section on. If they do not, switch it off.',
            'claims' => 'Remove unverified claims such as licensed, insured, 24/7, award-winning, or family-owned unless they already appear in the business notes.',
            'seo-snippet' => 'Lengthen the tagline and about text so a search snippet can be at least 70 characters, without inventing facts.',
            'seo-local' => 'Mention the known city in the headline and about text. Do not invent an address.',
            'seo-service-pages' => 'Expand each service description with unique local detail already known. Do not invent credentials.',
            'alt' => 'Write short, factual captions in the about or gallery copy that describe the photos. Do not invent what the photos show.',
            'lang' => 'Do not change facts. Keep the copy in clear English.',
            'viewport' => 'Keep the layout simple so it works on a phone. Do not add new claims.',
            'image-weight' => 'Do not embed large images in the copy. Refer to existing photos only.',
            default => null,
        };
    }
}

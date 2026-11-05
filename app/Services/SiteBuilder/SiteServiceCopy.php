<?php

namespace App\Services\SiteBuilder;

use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Turns a business category, and any services Google already listed, into four
 * service pages with real copy. Anything beyond those four is named on the
 * services page and does not get its own page.
 */
class SiteServiceCopy
{
    public const PAGED = 4;

    /**
     * Shortest marketing blurb we will leave alone on a service card.
     */
    public const MIN_DESCRIPTION = 220;

    /**
     * Shortest service-page body we will leave alone.
     */
    public const MIN_BODY = 360;

    /**
     * @param  list<array{title: string, summary?: string, description?: string, body?: string}>  $fillers
     * @param  list<array{title?: string, description?: string, body?: string, slug?: string, image_path?: string}>  $proposed
     * @return array{services: list<array{title: string, description: string, body: string, slug: string, image_path?: string}>, additional_services: list<string>}
     */
    public function build(Lead $lead, array $fillers = [], array $proposed = []): array
    {
        $real = $this->realTitles($lead, $proposed);
        $titles = $real;

        foreach ($fillers as $filler) {
            if (count($titles) >= self::PAGED) {
                break;
            }

            $title = trim((string) ($filler['title'] ?? ''));

            if ($title !== '' && ! $this->alreadyListed($titles, $title)) {
                $titles[] = $title;
            }
        }

        foreach ($this->fallbackTitles($lead) as $title) {
            if (count($titles) >= self::PAGED) {
                break;
            }

            if (! $this->alreadyListed($titles, $title)) {
                $titles[] = $title;
            }
        }

        $paged = array_slice($titles, 0, self::PAGED);
        $additional = array_values(array_filter(
            $real,
            fn (string $title) => ! $this->alreadyListed($paged, $title),
        ));

        $services = [];
        $taken = [];

        foreach ($paged as $title) {
            $known = $this->known($title, $proposed, $fillers);
            $written = $this->write($lead, $title, $known['summary'], $known['body']);
            $slug = SitePages::uniqueSlug($known['slug'], $title, $taken);
            $taken[] = $slug;

            $service = [
                'title' => $title,
                'description' => $written['description'],
                'body' => $written['body'],
                'slug' => $slug,
            ];

            if (filled($known['image_path'])) {
                $service['image_path'] = $known['image_path'];
            }

            $services[] = $service;
        }

        return [
            'services' => $services,
            'additional_services' => array_slice($additional, 0, 8),
        ];
    }

    /**
     * Fill thin descriptions and empty bodies on an existing site without changing its service list.
     *
     * @param  list<array{title: string, description?: string, body?: string, slug?: string, image_path?: string}>  $services
     * @param  list<array{title: string, summary?: string, description?: string, body?: string}>  $fillers
     * @return list<array{title: string, description: string, body: string, slug?: string, image_path?: string}>
     */
    public function fillThinCopy(Lead $lead, array $services, array $fillers = []): array
    {
        $filled = [];

        foreach ($services as $service) {
            $title = trim((string) ($service['title'] ?? ''));

            if ($title === '' || $this->isCategoryLabel($title, $lead->category)) {
                continue;
            }

            $known = $this->known($title, [$service], $fillers);
            $written = $this->write(
                $lead,
                $title,
                $known['summary'] !== '' ? $known['summary'] : trim((string) ($service['description'] ?? '')),
                trim((string) ($service['body'] ?? '')),
            );

            $row = [
                ...$service,
                'title' => $title,
                'description' => $written['description'],
                'body' => $written['body'],
            ];

            $filled[] = $row;
        }

        return $filled;
    }

    /**
     * Make sure a site shows four solid service pages. Empty listings and category labels
     * are replaced. Thin copy is filled. Extra names stay on the services page only.
     *
     * @param  array<string, mixed>  $content
     * @param  list<array{title: string, summary?: string, description?: string, body?: string}>  $fillers
     * @return array<string, mixed>
     */
    public function ensureReady(Lead $lead, array $content, array $fillers = []): array
    {
        $proposed = array_values(array_filter(
            $content['services'] ?? [],
            'is_array',
        ));

        if ($this->onlyCategoryLabels($proposed, $lead->category)) {
            $proposed = [];
        }

        $needsRebuild = $proposed === []
            || count($proposed) !== self::PAGED
            || $this->hasThinCopy($proposed);

        if (! $needsRebuild) {
            return $content;
        }

        $shaped = $this->build($lead, $fillers, $proposed);
        $content['services'] = $shaped['services'];

        if ($shaped['additional_services'] !== []) {
            $content['additional_services'] = $shaped['additional_services'];
        }

        return $content;
    }

    /**
     * @param  list<array{title?: string, description?: string, body?: string}>  $proposed
     * @return list<string>
     */
    protected function realTitles(Lead $lead, array $proposed): array
    {
        $titles = [];

        foreach ([...$proposed, ...array_map(fn (string $title) => ['title' => $title], $lead->listing_services ?? [])] as $service) {
            $title = trim((string) ($service['title'] ?? ''));

            if ($title === '' || $this->isCategoryLabel($title, $lead->category) || $this->alreadyListed($titles, $title)) {
                continue;
            }

            $titles[] = $title;
        }

        return $titles;
    }

    /**
     * A Google category such as "Handyman/Handywoman/Handyperson" is not a service.
     */
    protected function isCategoryLabel(string $title, ?string $category): bool
    {
        if (str_contains($title, '/')) {
            return true;
        }

        if ($category === null || trim($category) === '') {
            return false;
        }

        return Str::lower($title) === Str::lower(trim($category));
    }

    /**
     * @param  list<array{title?: string}>  $services
     */
    protected function onlyCategoryLabels(array $services, ?string $category): bool
    {
        if ($services === []) {
            return true;
        }

        foreach ($services as $service) {
            $title = trim((string) ($service['title'] ?? ''));

            if ($title !== '' && ! $this->isCategoryLabel($title, $category)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{description?: string, body?: string}>  $services
     */
    protected function hasThinCopy(array $services): bool
    {
        foreach ($services as $service) {
            if (mb_strlen(trim((string) ($service['description'] ?? ''))) < self::MIN_DESCRIPTION
                || mb_strlen(trim((string) ($service['body'] ?? ''))) < self::MIN_BODY) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $titles
     */
    protected function alreadyListed(array $titles, string $title): bool
    {
        $needle = Str::lower($title);

        foreach ($titles as $existing) {
            if (Str::lower($existing) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function fallbackTitles(Lead $lead): array
    {
        $noun = Str::lower(trim(Str::before((string) ($lead->category ?: 'local service'), '/')));

        if ($noun === '') {
            $noun = 'local service';
        }

        return [
            Str::title($noun).' visits',
            'Repairs',
            'Installations',
            'Maintenance',
        ];
    }

    /**
     * @param  list<array{title?: string, description?: string, body?: string, slug?: string, image_path?: string}>  $proposed
     * @param  list<array{title: string, summary?: string, description?: string, body?: string}>  $fillers
     * @return array{summary: string, body: string, slug: ?string, image_path: ?string}
     */
    protected function known(string $title, array $proposed, array $fillers): array
    {
        $summary = '';
        $body = '';
        $slug = null;
        $imagePath = null;

        foreach ([...$proposed, ...$fillers] as $service) {
            if (Str::lower(trim((string) ($service['title'] ?? ''))) !== Str::lower($title)) {
                continue;
            }

            $summary = trim((string) ($service['summary'] ?? $service['description'] ?? ''));
            $body = trim((string) ($service['body'] ?? ''));
            $slug = filled($service['slug'] ?? null) ? (string) $service['slug'] : null;
            $imagePath = filled($service['image_path'] ?? null) ? (string) $service['image_path'] : null;

            break;
        }

        return [
            'summary' => $summary,
            'body' => $body,
            'slug' => $slug,
            'image_path' => $imagePath,
        ];
    }

    /**
     * @return array{description: string, body: string}
     */
    protected function write(Lead $lead, string $title, string $summary, string $body): array
    {
        $place = filled($lead->city) ? ' in '.$lead->city : '';
        $name = $lead->name;
        $work = Str::lower($title);

        if ($summary === '') {
            $summary = "{$name} takes care of {$work}{$place} for people who want the job done carefully and explained in plain language.";
        }

        $description = $summary;

        if (mb_strlen($description) < self::MIN_DESCRIPTION) {
            $description = trim($summary.' '
                ."The work is talked through before it starts, so you know what will be done{$place}."
                .' When the job is finished, the space is left tidy and ready to use.'
                .' Get in touch if you want to check whether this is the right fit.');
        }

        if (mb_strlen($body) < self::MIN_BODY) {
            $seed = $body !== '' ? $body : $description;
            $body = trim($seed)
                ."\n\n"
                ."You say what you need, {$name} looks at the job, and you agree the next step before anything begins. "
                .'Questions are welcome along the way, and the finished work is checked before we leave. '
                .'If you are not sure this is the right service, send a message and we will talk it through.';
        }

        return [
            'description' => Str::limit($description, SiteContentSchema::SERVICE_DESCRIPTION_MAX, ''),
            'body' => Str::limit($body, 5000, ''),
        ];
    }
}

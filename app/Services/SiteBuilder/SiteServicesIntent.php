<?php

namespace App\Services\SiteBuilder;

use App\Models\Lead;
use Illuminate\Support\Str;

/**
 * Deterministic service list changes from plain-English chat.
 */
class SiteServicesIntent
{
    /**
     * @param  array<string, mixed>  $content
     * @return array{content: array<string, mixed>, changed: bool}
     */
    public function apply(Lead $lead, array $content, string $instruction): array
    {
        $services = array_values(array_filter($content['services'] ?? [], 'is_array'));
        $changed = false;

        if ($this->wantsRemoveAll($instruction)) {
            if ($services !== []) {
                $content['services'] = [];
                SiteEditExtras::add(__('• Removed all services'));
                $changed = true;
            }

            return ['content' => $content, 'changed' => $changed];
        }

        $removeTitle = $this->removeTitle($instruction);

        if ($removeTitle !== null) {
            $before = count($services);
            $services = array_values(array_filter(
                $services,
                fn (array $service) => ! Str::contains(Str::lower((string) ($service['title'] ?? '')), Str::lower($removeTitle)),
            ));

            if (count($services) !== $before) {
                $content['services'] = $services;
                SiteEditExtras::add(__('• Removed service “:title”', ['title' => $removeTitle]));
                $changed = true;
            }

            return ['content' => $content, 'changed' => $changed];
        }

        $titles = $this->addTitles($instruction);

        if ($titles === []) {
            return ['content' => $content, 'changed' => false];
        }

        $copy = new SiteServiceCopy;
        $taken = array_map(fn (array $service) => (string) ($service['slug'] ?? ''), $services);
        $added = 0;

        foreach ($titles as $title) {
            if (count($services) >= SiteContentSchema::MAX_SERVICES) {
                break;
            }

            if ($this->alreadyListed($services, $title)) {
                continue;
            }

            $draft = $copy->forTitle($lead, $title, $taken);
            $taken[] = $draft['slug'];
            $services[] = $draft;
            $added++;
        }

        if ($added > 0) {
            $content['services'] = $services;
            $content = $this->ensureServicesPage($content);
            SiteEditExtras::add(__('• Added :count service(s)', ['count' => $added]));
            $changed = true;
        }

        return ['content' => $content, 'changed' => $changed];
    }

    public function matches(string $instruction): bool
    {
        return $this->wantsRemoveAll($instruction)
            || $this->removeTitle($instruction) !== null
            || $this->addTitles($instruction) !== [];
    }

    public function wantsRemoveAll(string $instruction): bool
    {
        return preg_match('/\b(remove|delete|clear)\b.{0,40}\b(all|every)\b.{0,20}\bservices?\b/i', $instruction) === 1;
    }

    public function removeTitle(string $instruction): ?string
    {
        if (preg_match('/\b(remove|delete)\b.{0,30}\bservice\b.{0,20}(?:called|named|titled)?\s*[\'\"]([^\'\"]{2,80})[\'\"]/i', $instruction, $match) === 1) {
            return trim($match[2]);
        }

        if (preg_match('/\b(remove|delete)\b.{0,20}\bservice\b\s+[\'\"]([^\'\"]{2,80})[\'\"]/i', $instruction, $match) === 1) {
            return trim($match[2]);
        }

        if (preg_match('/\b(remove|delete)\b.{0,10}[\'\"]([^\'\"]{2,80})[\'\"]\s+service\b/i', $instruction, $match) === 1) {
            return trim($match[2]);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function addTitles(string $instruction): array
    {
        if (preg_match('/\b(add|create|include)\b.{0,40}\bservices?\b/i', $instruction) !== 1) {
            return [];
        }

        if (preg_match('/\bservices?\b(?:\s*(?:called|named|:|\s))+(.+)$/is', $instruction, $match) !== 1) {
            return [];
        }

        $chunk = trim($match[1]);
        $chunk = preg_replace('/[.\s]+$/', '', $chunk) ?? $chunk;

        if (preg_match_all('/[\'\"]([^\'\"]{2,80})[\'\"]/', $chunk, $quoted) > 0) {
            return array_values(array_unique(array_map('trim', $quoted[1])));
        }

        $parts = preg_split('/\s*(?:,|;|\band\b)\s*/i', $chunk) ?: [];
        $titles = [];

        foreach ($parts as $part) {
            $title = trim($part, " \t\n\r\0\x0B\"'");

            if ($title === '' || Str::length($title) > 60) {
                continue;
            }

            if (preg_match('/\b(please|thanks|page|section|menu)\b/i', $title) === 1) {
                continue;
            }

            $titles[] = Str::limit($title, 60, '');
        }

        return array_values(array_unique($titles));
    }

    /**
     * @param  list<array<string, mixed>>  $services
     */
    protected function alreadyListed(array $services, string $title): bool
    {
        $needle = Str::lower($title);

        foreach ($services as $service) {
            if (Str::lower((string) ($service['title'] ?? '')) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function ensureServicesPage(array $content): array
    {
        $pages = array_values($content['pages'] ?? SitePages::DEFAULT);

        if (! in_array('services', $pages, true)) {
            $pages[] = 'services';
            $content['pages'] = $pages;
        }

        return $content;
    }
}

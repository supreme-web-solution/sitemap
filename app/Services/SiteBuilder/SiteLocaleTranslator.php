<?php

namespace App\Services\SiteBuilder;

use App\Ai\Agents\SiteLocaleCopyTranslator;
use App\Ai\Support\LocaleCopySubmission;
use App\Models\Site;
use App\Services\Ai\AiAvailability;
use Illuminate\Support\Facades\Log;
use Throwable;

class SiteLocaleTranslator
{
    /**
     * Translate the public copy for one locale using the Laravel AI agent.
     *
     * @return array<string, mixed>
     */
    public function translate(Site $site, string $locale): array
    {
        if (! AiAvailability::anthropicConfigured()) {
            return $this->fallback($site, $locale);
        }

        $source = $this->sourceBundle($site->content);
        $submission = new LocaleCopySubmission;

        try {
            (new SiteLocaleCopyTranslator($submission))->prompt(
                'Target locale: '.$locale."\n\nCopy JSON:\n".json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );

            if ($submission->bundle !== null) {
                return $submission->bundle;
            }
        } catch (Throwable $exception) {
            Log::warning('Site locale translation failed.', ['site_id' => $site->id, 'locale' => $locale, 'message' => $exception->getMessage()]);
        }

        return $this->fallback($site, $locale);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function sourceBundle(array $content): array
    {
        return [
            'headline' => $content['headline'] ?? '',
            'tagline' => $content['tagline'] ?? '',
            'cta_label' => $content['cta_label'] ?? '',
            'about' => $content['about'] ?? '',
            'services' => array_map(
                fn (array $service) => [
                    'title' => $service['title'] ?? '',
                    'description' => $service['description'] ?? '',
                    'body' => $service['body'] ?? '',
                ],
                $content['services'] ?? [],
            ),
            'faqs' => $content['faqs'] ?? [],
            'copy' => $content['copy'] ?? [],
            'page_labels' => $content['page_labels'] ?? SitePages::PAGE_LABELS,
            'custom_pages' => $content['custom_pages'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fallback(Site $site, string $locale): array
    {
        $bundle = $this->sourceBundle($site->content);
        $label = SiteLocales::catalog()[$locale] ?? strtoupper($locale);
        $bundle['headline'] = '['.$label.'] '.$bundle['headline'];

        return $bundle;
    }
}

<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiteLocaleTranslator
{
    /**
     * Translate the public copy for one locale using the configured AI provider.
     *
     * @return array<string, mixed>
     */
    public function translate(Site $site, string $locale): array
    {
        $apiKey = config('services.openai.key');

        if (! filled($apiKey)) {
            return $this->fallback($site, $locale);
        }

        $source = $this->sourceBundle($site->content);

        try {
            $response = Http::baseUrl('https://api.openai.com/v1')
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout(60)
                ->post('chat/completions', [
                    'model' => config('services.openai.model'),
                    'max_completion_tokens' => 4000,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You translate website copy. Return only JSON matching the input shape. Keep facts, names, phone numbers and addresses unchanged. Do not invent offers.',
                        ],
                        [
                            'role' => 'user',
                            'content' => 'Target locale: '.$locale."\n\nCopy JSON:\n".json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ])
                ->throw();

            $content = $response->json('choices.0.message.content');
            $decoded = is_string($content) ? json_decode($content, true) : null;

            if (! is_array($decoded)) {
                return $this->fallback($site, $locale);
            }

            return $decoded;
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Site locale translation failed.', ['site_id' => $site->id, 'locale' => $locale, 'message' => $exception->getMessage()]);

            return $this->fallback($site, $locale);
        }
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

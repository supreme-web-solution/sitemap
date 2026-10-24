<?php

namespace App\Services\SiteBuilder;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpenAiImageGenerator
{
    /**
     * Generate a photo for a lead and store it on the public disk.
     *
     * @return array{path: string, url: string}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function generate(string $prompt, int $teamId, int $leadId): array
    {
        $apiKey = config('services.openai.key');

        if (! filled($apiKey)) {
            throw new \RuntimeException(__('AI image generation is not configured.'));
        }

        $response = Http::baseUrl('https://api.openai.com/v1')
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(120)
            ->post('images/generations', [
                'model' => config('sites.image_model'),
                'prompt' => Str::limit($prompt, 900, ''),
                'size' => '1024x1024',
            ])
            ->throw();

        $binary = $this->binaryFromResponse($response->json());
        $path = "teams/{$teamId}/leads/{$leadId}/ai-".Str::lower(Str::random(10)).'.png';

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function binaryFromResponse(?array $payload): string
    {
        $b64 = $payload['data'][0]['b64_json'] ?? null;

        if (is_string($b64) && $b64 !== '') {
            $decoded = base64_decode($b64, true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        $url = $payload['data'][0]['url'] ?? null;

        if (is_string($url) && $url !== '') {
            return Http::timeout(60)->get($url)->throw()->body();
        }

        throw new \RuntimeException(__('The image provider returned an empty response.'));
    }
}

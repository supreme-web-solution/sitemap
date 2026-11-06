<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteImageGenerationPlan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenRouterImageGenerator
{
    use StoresLeadGeneratedPhoto;

    private const DRIVER = 'openrouter';

    /**
     * @return array{path: string, url: string}
     *
     * @throws ConnectionException
     * @throws \RuntimeException
     */
    public function generate(SiteImageGenerationPlan $plan, int $teamId, int $leadId): array
    {
        $apiKey = config('services.openrouter.key');

        if (! filled($apiKey)) {
            throw new \RuntimeException(ImageGenerationMessages::notConfiguredMessage());
        }

        try {
            $response = $this->client()
                ->post('images', [
                    'model' => config('sites.openrouter_image_model'),
                    'prompt' => $plan->prompt,
                    'n' => 1,
                    'aspect_ratio' => $plan->aspectRatio,
                    'output_format' => 'png',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('OpenRouter image request connection failed.', ['message' => $exception->getMessage()]);

            throw new \RuntimeException(ImageGenerationMessages::unavailableMessage());
        }

        if ($response->failed()) {
            Log::warning('OpenRouter image request failed.', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            throw new \RuntimeException(ImageGenerationMessages::messageForFailedResponse($response));
        }

        $payload = $response->json();
        $binary = $this->binaryFromImageApiPayload($payload, self::DRIVER);
        $mediaType = $payload['data'][0]['media_type'] ?? null;

        return $this->storeLeadPhotoBinary(
            $binary,
            $teamId,
            $leadId,
            $this->extensionFromMediaType(is_string($mediaType) ? $mediaType : null),
        );
    }

    /**
     * @return array{path: string, url: string}
     *
     * @throws ConnectionException
     * @throws \RuntimeException
     */
    public function improve(SiteImageGenerationPlan $plan, string $source, int $teamId, int $leadId): array
    {
        $apiKey = config('services.openrouter.key');

        if (! filled($apiKey)) {
            throw new \RuntimeException(ImageGenerationMessages::notConfiguredMessage());
        }

        try {
            $response = $this->client()
                ->post('chat/completions', [
                    'model' => config('sites.openrouter_image_model'),
                    'modalities' => ['image', 'text'],
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $plan->prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => $this->imageDataUrl($source)]],
                        ],
                    ]],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('OpenRouter image edit connection failed.', ['message' => $exception->getMessage()]);

            throw new \RuntimeException(ImageGenerationMessages::unavailableMessage());
        }

        if ($response->failed()) {
            Log::warning('OpenRouter image edit failed.', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            throw new \RuntimeException(ImageGenerationMessages::messageForFailedResponse($response));
        }

        $binary = $this->binaryFromEditPayload($response->json(), self::DRIVER);

        return $this->storeLeadPhotoBinary($binary, $teamId, $leadId);
    }

    protected function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.openrouter.url', 'https://openrouter.ai/api/v1'), '/');

        $client = Http::baseUrl($baseUrl.'/')
            ->withToken((string) config('services.openrouter.key'))
            ->acceptJson()
            ->timeout(120);

        $headers = array_filter([
            'HTTP-Referer' => config('services.openrouter.referer'),
            'X-Title' => config('services.openrouter.title'),
        ]);

        if ($headers !== []) {
            $client = $client->withHeaders($headers);
        }

        return $client;
    }
}

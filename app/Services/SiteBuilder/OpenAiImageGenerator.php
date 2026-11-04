<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteImageGenerationPlan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenAiImageGenerator
{
    use StoresLeadGeneratedPhoto;

    private const DRIVER = 'openai';

    /**
     * @return array{path: string, url: string}
     *
     * @throws ConnectionException
     * @throws \RuntimeException
     */
    public function generate(SiteImageGenerationPlan $plan, int $teamId, int $leadId): array
    {
        $apiKey = config('services.openai.key');

        if (! filled($apiKey)) {
            throw new \RuntimeException(ImageGenerationMessages::notConfiguredMessage());
        }

        try {
            $response = Http::baseUrl('https://api.openai.com/v1')
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout(120)
                ->post('images/generations', [
                    'model' => config('sites.image_model'),
                    'prompt' => $plan->prompt,
                    'size' => $plan->openAiSize(),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('OpenAI image request connection failed.', ['message' => $exception->getMessage()]);

            throw new \RuntimeException(ImageGenerationMessages::unavailableMessage());
        }

        if ($response->failed()) {
            Log::warning('OpenAI image request failed.', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            throw new \RuntimeException(ImageGenerationMessages::messageForFailedResponse($response));
        }

        $binary = $this->binaryFromImageApiPayload($response->json(), self::DRIVER);

        return $this->storeLeadPhotoBinary($binary, $teamId, $leadId);
    }
}

<?php

namespace App\Services\SiteBuilder;

use App\Services\Media\SiteImageStorage;
use Illuminate\Support\Facades\Http;

trait StoresLeadGeneratedPhoto
{
    /**
     * @return array{path: string, url: string}
     */
    protected function storeLeadPhotoBinary(string $binary, int $teamId, int $leadId, string $extension = 'png'): array
    {
        return app(SiteImageStorage::class)->storeBinary($binary, $teamId, $leadId, $extension);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function binaryFromImageApiPayload(?array $payload, string $driver): string
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
            try {
                return Http::timeout(60)->get($url)->throw()->body();
            } catch (\Throwable) {
                throw new \RuntimeException(ImageGenerationMessages::unavailableMessage());
            }
        }

        throw new \RuntimeException(ImageGenerationMessages::unavailableMessage());
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function binaryFromEditPayload(?array $payload, string $driver): string
    {
        try {
            return $this->binaryFromImageApiPayload($payload, $driver);
        } catch (\RuntimeException $exception) {
            $fromChat = $this->binaryFromChatImage($payload);

            if ($fromChat !== null) {
                return $fromChat;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function binaryFromChatImage(?array $payload): ?string
    {
        $images = $payload['choices'][0]['message']['images'] ?? [];

        if (is_array($images)) {
            foreach ($images as $image) {
                if (! is_array($image)) {
                    continue;
                }

                $decoded = $this->binaryFromImageReference($image['image_url']['url'] ?? $image['url'] ?? null);

                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }

        $content = $payload['choices'][0]['message']['content'] ?? null;

        if (! is_array($content)) {
            return null;
        }

        foreach ($content as $part) {
            if (! is_array($part)) {
                continue;
            }

            $decoded = $this->binaryFromImageReference($part['image_url']['url'] ?? null);

            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }

    protected function binaryFromImageReference(mixed $reference): ?string
    {
        if (! is_string($reference) || $reference === '') {
            return null;
        }

        if (preg_match('#^data:image/[^;]+;base64,(.+)$#', $reference, $matches) === 1) {
            $decoded = base64_decode($matches[1], true);

            return $decoded === false || $decoded === '' ? null : $decoded;
        }

        return null;
    }

    protected function imageDataUrl(string $binary): string
    {
        $mime = 'image/jpeg';

        if (str_starts_with($binary, "\x89PNG")) {
            $mime = 'image/png';
        } elseif (str_starts_with($binary, 'GIF8')) {
            $mime = 'image/gif';
        } elseif (str_starts_with($binary, 'RIFF') && str_contains(substr($binary, 0, 16), 'WEBP')) {
            $mime = 'image/webp';
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    protected function extensionFromMediaType(?string $mediaType): string
    {
        return match ($mediaType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'png',
        };
    }
}

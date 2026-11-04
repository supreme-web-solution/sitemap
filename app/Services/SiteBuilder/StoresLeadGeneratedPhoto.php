<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait StoresLeadGeneratedPhoto
{
    /**
     * @return array{path: string, url: string}
     */
    protected function storeLeadPhotoBinary(string $binary, int $teamId, int $leadId, string $extension = 'png'): array
    {
        $extension = ltrim(strtolower($extension), '.');
        $path = "teams/{$teamId}/leads/{$leadId}/ai-".Str::lower(Str::random(10)).'.'.$extension;

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
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

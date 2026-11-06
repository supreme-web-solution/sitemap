<?php

namespace App\Services\Media;

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Store and serve lead/site images. Prefers Cloudinary (WebP + f_auto/q_auto delivery)
 * when configured; otherwise uses the local public disk.
 */
class SiteImageStorage
{
    public const PREFIX = 'cloudinary:';

    /**
     * Delivery transforms: auto format (WebP/AVIF when supported), auto quality, capped width.
     */
    private const DELIVERY = 'f_auto,q_auto,c_limit,w_2000';

    public function configured(): bool
    {
        if (filled(config('services.cloudinary.url'))) {
            return true;
        }

        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.key'))
            && filled(config('services.cloudinary.secret'));
    }

    public function isCloudinary(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::PREFIX);
    }

    /**
     * @return array{path: string, url: string}
     */
    public function storeUploadedFile(UploadedFile $file, int $teamId, int $leadId): array
    {
        if ($this->configured()) {
            return $this->uploadToCloudinary($file->getRealPath(), $teamId, $leadId, $file->getClientOriginalExtension() ?: 'jpg');
        }

        $path = $file->store($this->localDirectory($teamId, $leadId), 'public');

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    /**
     * @return array{path: string, url: string}
     */
    public function storeBinary(string $binary, int $teamId, int $leadId, string $extension = 'png'): array
    {
        $extension = ltrim(strtolower($extension), '.') ?: 'png';

        if ($this->configured()) {
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                default => 'image/png',
            };

            return $this->uploadToCloudinary(
                'data:'.$mime.';base64,'.base64_encode($binary),
                $teamId,
                $leadId,
                $extension,
            );
        }

        $path = $this->localDirectory($teamId, $leadId).'/ai-'.Str::lower(Str::random(10)).'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ];
    }

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if ($this->isCloudinary($path)) {
            return $this->cloudinaryDeliveryUrl($this->publicId($path));
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @param  list<string|null>  $paths
     * @return list<string>
     */
    public function urls(array $paths): array
    {
        return array_values(array_filter(array_map(
            fn (?string $path) => $this->url($path),
            $paths,
        )));
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if ($this->isCloudinary($path)) {
            $this->destroyCloudinary($this->publicId($path));

            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * Delete a replaced asset immediately when it is local; Cloudinary assets are kept until
     * an undo snapshot is discarded so Undo can restore them.
     */
    public function forgetReplaced(?string $path): void
    {
        if ($path === null || $path === '' || $this->isCloudinary($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public function publicId(string $path): string
    {
        return Str::after($path, self::PREFIX);
    }

    protected function localDirectory(int $teamId, int $leadId): string
    {
        return "teams/{$teamId}/leads/{$leadId}";
    }

    /**
     * @return array{path: string, url: string}
     */
    protected function uploadToCloudinary(string $file, int $teamId, int $leadId, string $extension): array
    {
        $folder = trim((string) config('services.cloudinary.folder', 'mapmatic'), '/');
        $folder = $folder === '' ? 'mapmatic' : $folder;

        try {
            $result = $this->uploadApi()->upload($file, [
                'folder' => "{$folder}/teams/{$teamId}/leads/{$leadId}",
                'resource_type' => 'image',
                'unique_filename' => true,
                'overwrite' => false,
                'format' => 'webp',
                'quality' => 'auto:good',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Cloudinary image upload failed.', [
                'message' => Str::limit($exception->getMessage(), 300),
                'team_id' => $teamId,
                'lead_id' => $leadId,
            ]);

            throw new \RuntimeException(__('The image could not be uploaded to Cloudinary. Check your Cloudinary credentials.'));
        }

        $publicId = (string) ($result['public_id'] ?? '');

        if ($publicId === '') {
            throw new \RuntimeException(__('The image could not be uploaded to Cloudinary. Check your Cloudinary credentials.'));
        }

        $path = self::PREFIX.$publicId;

        return [
            'path' => $path,
            'url' => $this->url($path) ?? (string) ($result['secure_url'] ?? ''),
        ];
    }

    protected function destroyCloudinary(string $publicId): void
    {
        if ($publicId === '') {
            return;
        }

        try {
            $this->uploadApi()->destroy($publicId, ['resource_type' => 'image']);
        } catch (\Throwable $exception) {
            Log::warning('Cloudinary image delete failed.', [
                'public_id' => $publicId,
                'message' => Str::limit($exception->getMessage(), 300),
            ]);
        }
    }

    protected function cloudinaryDeliveryUrl(string $publicId): string
    {
        $cloud = $this->cloudName();
        $encoded = implode('/', array_map('rawurlencode', explode('/', $publicId)));

        return "https://res.cloudinary.com/{$cloud}/image/upload/".self::DELIVERY."/{$encoded}";
    }

    protected function cloudName(): string
    {
        $fromConfig = (string) config('services.cloudinary.cloud_name', '');

        if ($fromConfig !== '') {
            return $fromConfig;
        }

        $url = (string) config('services.cloudinary.url', '');

        if (preg_match('#@([^/?]+)#', $url, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    protected function uploadApi(): UploadApi
    {
        return new UploadApi($this->configuration());
    }

    protected function configuration(): Configuration
    {
        $url = config('services.cloudinary.url');

        if (filled($url)) {
            return Configuration::instance((string) $url);
        }

        return Configuration::instance([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.key'),
                'api_secret' => config('services.cloudinary.secret'),
            ],
            'url' => [
                'secure' => (bool) config('services.cloudinary.secure', true),
            ],
        ]);
    }
}

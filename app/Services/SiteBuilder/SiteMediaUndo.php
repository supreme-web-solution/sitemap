<?php

namespace App\Services\SiteBuilder;

use App\Models\Lead;
use App\Models\Site;
use App\Services\Media\SiteImageStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps one undo step for visual-editor logo/photo/service image changes.
 * Local files are copied; Cloudinary assets keep their public id until the snapshot is discarded.
 */
class SiteMediaUndo
{
    private const CACHE_PREFIX = 'site_media_undo:';

    public function __construct(protected SiteImageStorage $images) {}

    public function remember(Site $site, Lead $lead): void
    {
        $cacheKey = self::CACHE_PREFIX.$site->id;
        $previous = Cache::get($cacheKey);

        if (is_array($previous)) {
            $this->forgetStoredPaths($previous);
        }

        $photos = $lead->photo_paths ?? [];
        $servicePaths = [];

        foreach (array_values($site->content['services'] ?? []) as $service) {
            $servicePaths[] = is_array($service) && is_string($service['image_path'] ?? null)
                ? $this->preservePath($service['image_path'])
                : null;
        }

        $imagePaths = [];

        foreach (SiteImagePlacements::KEYS as $placement) {
            $stored = is_array($site->content['images'] ?? null) ? ($site->content['images'][$placement] ?? null) : null;
            $imagePaths[$placement] = is_string($stored) ? $this->preservePath($stored) : null;
        }

        Cache::put($cacheKey, [
            'logo_path' => $this->preservePath($lead->logo_path),
            'photo_paths' => array_map(
                fn (?string $path) => $path !== null ? $this->preservePath($path) : null,
                $photos,
            ),
            'service_image_paths' => $servicePaths,
            'placement_paths' => $imagePaths,
        ], now()->addDay());
    }

    /**
     * @return array{logo_path: ?string, photo_paths: list<?string>, service_image_paths?: list<?string>}|null
     */
    public function pull(Site $site): ?array
    {
        $key = self::CACHE_PREFIX.$site->id;
        $snapshot = Cache::pull($key);

        return is_array($snapshot) ? $snapshot : null;
    }

    public function has(Site $site): bool
    {
        return Cache::has(self::CACHE_PREFIX.$site->id);
    }

    /**
     * @param  array{logo_path: ?string, photo_paths: list<?string>, service_image_paths?: list<?string>}  $snapshot
     */
    public function forgetStoredPaths(array $snapshot): void
    {
        $this->forgetPreserved($snapshot['logo_path'] ?? null);

        foreach ($snapshot['photo_paths'] ?? [] as $path) {
            $this->forgetPreserved($path);
        }

        foreach ($snapshot['service_image_paths'] ?? [] as $path) {
            $this->forgetPreserved($path);
        }

        foreach ($snapshot['placement_paths'] ?? [] as $path) {
            $this->forgetPreserved($path);
        }
    }

    protected function preservePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        if ($this->images->isCloudinary($path)) {
            return $path;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $directory = dirname($path).'/.undo';
        $disk->makeDirectory($directory);
        $destination = $directory.'/'.Str::uuid().($extension !== '' ? ".{$extension}" : '');
        $disk->copy($path, $destination);

        return $destination;
    }

    protected function forgetPreserved(?string $path): void
    {
        if ($path === null) {
            return;
        }

        if ($this->images->isCloudinary($path)) {
            $this->images->delete($path);

            return;
        }

        if (str_contains($path, '/.undo/')) {
            Storage::disk('public')->delete($path);
        }
    }
}

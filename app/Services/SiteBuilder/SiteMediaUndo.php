<?php

namespace App\Services\SiteBuilder;

use App\Models\Lead;
use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps one undo step for visual-editor logo/photo changes (files are copied before replace).
 */
class SiteMediaUndo
{
    private const CACHE_PREFIX = 'site_media_undo:';

    public function remember(Site $site, Lead $lead): void
    {
        $key = self::CACHE_PREFIX.$site->id;
        $previous = Cache::get($key);

        if (is_array($previous)) {
            $this->forgetStoredPaths($previous);
        }

        $photos = $lead->photo_paths ?? [];

        Cache::put($key, [
            'logo_path' => $this->duplicatePath($lead->logo_path),
            'photo_paths' => array_map(
                fn (?string $path) => $path !== null ? $this->duplicatePath($path) : null,
                $photos,
            ),
        ], now()->addDay());
    }

    /**
     * @return array{logo_path: ?string, photo_paths: list<?string>}|null
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
     * @param  array{logo_path: ?string, photo_paths: list<?string>}  $snapshot
     */
    public function forgetStoredPaths(array $snapshot): void
    {
        $this->forgetPath($snapshot['logo_path'] ?? null);

        foreach ($snapshot['photo_paths'] ?? [] as $path) {
            $this->forgetPath($path);
        }
    }

    protected function duplicatePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
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

    protected function forgetPath(?string $path): void
    {
        if ($path !== null && str_contains($path, '/.undo/')) {
            Storage::disk('public')->delete($path);
        }
    }
}

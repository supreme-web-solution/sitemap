<?php

namespace App\Services\SiteBuilder;

use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;

class SiteChatAttachment
{
    public const DISK = 'public';

    public const MAX_KILOBYTES = 5120;

    public const MAX_ATTACHMENTS = 5;

    public static function store(UploadedFile $file, Site $site): string
    {
        $site->loadMissing('lead');

        $teamId = $site->lead->team_id ?? $site->team_id;

        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'png';
        }

        $path = sprintf(
            'teams/%s/sites/%s/chat/%s.%s',
            $teamId,
            $site->id,
            Str::lower(Str::random(16)),
            $extension === 'jpeg' ? 'jpg' : $extension,
        );

        Storage::disk(self::DISK)->put($path, $file->getContent());

        return $path;
    }

    public static function url(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * @param  list<string>|string|null  $paths
     * @return list<string>
     */
    public static function normalizePaths(array|string|null $paths): array
    {
        if (is_string($paths)) {
            $paths = $paths !== '' ? [$paths] : [];
        }

        if (! is_array($paths)) {
            return [];
        }

        $normalized = [];

        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            if (! in_array($path, $normalized, true)) {
                $normalized[] = $path;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<string>
     */
    public static function storeMany(array $files, Site $site): array
    {
        $stored = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $stored[] = self::store($file, $site);
            }
        }

        return $stored;
    }

    /**
     * @param  list<string>|string|null  $paths
     * @return list<Image>
     */
    public static function imagesForPrompt(array|string|null $paths): array
    {
        $images = [];

        foreach (self::normalizePaths($paths) as $path) {
            if (! Storage::disk(self::DISK)->exists($path)) {
                continue;
            }

            $images[] = Image::fromStorage($path, self::DISK);
        }

        return $images;
    }

    /**
     * @param  list<string>|string|null  $paths
     */
    public static function visionPromptPrefix(array|string|null $paths): string
    {
        $count = count(self::normalizePaths($paths));

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return 'The user attached a screenshot of their site preview (same image in this message). '
                .'Identify which section and elements are visible (hero, headline, buttons, footer, etc.) '
                .'and tie your plan to what you see. If they reference "this" or "here", use the screenshot.';
        }

        return "The user attached {$count} screenshots with this message (in the order provided). "
            .'Each may show a different section or a different design reference. '
            .'Map each image to the part of their instruction it illustrates; when they ask for changes in multiple places, use all images together.';
    }

    /**
     * @param  list<string>  $paths
     * @return list<string|null>
     */
    public static function urls(array $paths): array
    {
        $urls = [];

        foreach (self::normalizePaths($paths) as $path) {
            $url = self::url($path);

            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}

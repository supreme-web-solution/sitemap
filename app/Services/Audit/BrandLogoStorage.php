<?php

namespace App\Services\Audit;

use App\Models\Lead;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandLogoStorage
{
    /**
     * @var list<string>
     */
    protected const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    public function __construct(protected SafeHttpFetcher $fetcher) {}

    /**
     * Download a logo from a public URL and attach it to the lead.
     */
    public function store(Lead $lead, string $logoUrl): bool
    {
        try {
            $page = $this->fetcher->fetch($logoUrl);
        } catch (UnsafeUrlException|ConnectionException) {
            return false;
        }

        if ($page['status'] < 200 || $page['status'] >= 300 || $page['body'] === '') {
            return false;
        }

        if (strlen($page['body']) > 512_000) {
            return false;
        }

        $extension = $this->extension($logoUrl, $page['body']);

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        $disk = Storage::disk('public');
        $directory = "teams/{$lead->team_id}/leads/{$lead->id}";
        $path = "{$directory}/".Str::uuid()->toString().".{$extension}";

        if (! $disk->put($path, $page['body'])) {
            return false;
        }

        if ($lead->logo_path) {
            $disk->delete($lead->logo_path);
        }

        $lead->forceFill(['logo_path' => $path])->save();

        return true;
    }

    protected function extension(string $logoUrl, string $body): string
    {
        $fromPath = strtolower(pathinfo(parse_url($logoUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        if (in_array($fromPath, self::ALLOWED_EXTENSIONS, true)) {
            return $fromPath === 'jpeg' ? 'jpg' : $fromPath;
        }

        if (str_starts_with($body, '<svg') || str_contains($body, '<svg')) {
            return 'svg';
        }

        if (str_starts_with($body, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }

        if (str_starts_with($body, "\xff\xd8\xff")) {
            return 'jpg';
        }

        if (str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a')) {
            return 'gif';
        }

        if (str_starts_with($body, 'RIFF') && str_contains(substr($body, 0, 16), 'WEBP')) {
            return 'webp';
        }

        return 'png';
    }
}

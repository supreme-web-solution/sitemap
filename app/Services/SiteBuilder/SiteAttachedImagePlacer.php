<?php

namespace App\Services\SiteBuilder;

use App\Http\Requests\Leads\UpdateLeadProfileRequest;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies a chat attachment onto the lead's site photos (hero or gallery).
 */
class SiteAttachedImagePlacer
{
    public const PLACEMENT_HERO = 'hero';

    public const PLACEMENT_GALLERY = 'gallery';

    /**
     * @throws \RuntimeException
     */
    public function place(Site $site, string $chatAttachmentPath, string $placement = self::PLACEMENT_HERO): string
    {
        $site->loadMissing('lead');
        $lead = $site->lead;

        if ($lead->team_id === null) {
            throw new \RuntimeException(__('This lead is missing a team.'));
        }

        if (! Storage::disk(SiteChatAttachment::DISK)->exists($chatAttachmentPath)) {
            throw new \RuntimeException(__('The attached image could not be found. Try uploading it again.'));
        }

        $extension = pathinfo($chatAttachmentPath, PATHINFO_EXTENSION) ?: 'png';
        $destination = sprintf(
            'teams/%s/leads/%s/chat-upload-%s.%s',
            $lead->team_id,
            $lead->id,
            Str::lower(Str::random(12)),
            $extension,
        );

        Storage::disk(SiteChatAttachment::DISK)->copy($chatAttachmentPath, $destination);

        $paths = array_values($lead->photo_paths ?? []);
        $placement = self::normalizePlacement($placement);

        if ($placement === self::PLACEMENT_HERO) {
            if (isset($paths[0])) {
                Storage::disk(SiteChatAttachment::DISK)->delete($paths[0]);
                $paths[0] = $destination;
            } elseif (count($paths) >= UpdateLeadProfileRequest::MAX_PHOTOS) {
                Storage::disk(SiteChatAttachment::DISK)->delete($destination);

                throw new \RuntimeException(__('A website can have up to :max photos.', ['max' => UpdateLeadProfileRequest::MAX_PHOTOS]));
            } else {
                array_unshift($paths, $destination);
            }

            $lead->forceFill(['photo_paths' => array_values($paths)])->save();

            return __('Your image is now the hero photo on this site.');
        }

        if (count($paths) >= UpdateLeadProfileRequest::MAX_PHOTOS) {
            Storage::disk(SiteChatAttachment::DISK)->delete($destination);

            throw new \RuntimeException(__('A website can have up to :max photos.', ['max' => UpdateLeadProfileRequest::MAX_PHOTOS]));
        }

        $paths[] = $destination;
        $lead->forceFill(['photo_paths' => array_values($paths)])->save();

        return __('Your image was added to the site gallery.');
    }

    public static function normalizePlacement(string $placement): string
    {
        $placement = Str::lower(trim($placement));

        if (in_array($placement, ['gallery', 'photos', 'photo', 'grid'], true)) {
            return self::PLACEMENT_GALLERY;
        }

        return self::PLACEMENT_HERO;
    }
}

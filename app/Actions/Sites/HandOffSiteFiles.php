<?php

namespace App\Actions\Sites;

use App\Jobs\BackupPublishedSite;
use App\Models\Site;
use Illuminate\Support\Facades\URL;

class HandOffSiteFiles
{
    /**
     * Unpublish the site on our hosting and return a private ZIP download link.
     *
     * @return array{downloadUrl: string, expiresOn: string}
     */
    public function handle(Site $site, string $email): array
    {
        $site->loadMissing(['lead', 'team']);

        if ($site->isPublished()) {
            $site->forceFill(['published_at' => null])->save();
        }

        BackupPublishedSite::dispatchSync($site);

        $expires = now()->addDays(30);

        $site->forceFill([
            'transferred_at' => now(),
            'transferred_to_email' => $email,
        ])->save();

        return [
            'downloadUrl' => URL::temporarySignedRoute('sites.handoff', $expires, ['site' => $site]),
            'expiresOn' => $expires->toFormattedDateString(),
        ];
    }
}

<?php

namespace App\Jobs;

use App\Actions\Sites\HandOffSiteFiles;
use App\Mail\SiteTransferEmail;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class TransferSiteOwnership implements ShouldQueue
{
    use Queueable;

    public function __construct(public Site $site, public string $email) {}

    /**
     * Take the site off our hosting and email the client that they now own the files.
     */
    public function handle(HandOffSiteFiles $handOffSiteFiles): void
    {
        $site = $this->site->fresh(['lead', 'team']);

        if ($site === null) {
            return;
        }

        $handoff = $handOffSiteFiles->handle($site, $this->email);

        Mail::to($this->email)->send(new SiteTransferEmail(
            $site->fresh(['lead', 'team']),
            $handoff['downloadUrl'],
            $handoff['expiresOn'],
        ));
    }
}

<?php

namespace App\Jobs;

use App\Mail\SiteTransferEmail;
use App\Models\Site;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class TransferSiteOwnership implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 40;

    public function __construct(public Site $site, public string $email)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    /**
     * Take the site off our hosting and email the client that they now own the files.
     */
    public function handle(): void
    {
        $site = $this->site->fresh(['lead', 'team']);

        if ($site === null) {
            return;
        }

        if ($site->isPublished()) {
            $site->forceFill(['published_at' => null])->save();
        }

        BackupPublishedSite::dispatchSync($site);

        $site->forceFill([
            'transferred_at' => now(),
            'transferred_to_email' => $this->email,
        ])->save();

        $expires = now()->addDays(30);

        Mail::to($this->email)->queue(new SiteTransferEmail(
            $site,
            URL::temporarySignedRoute('sites.handoff', $expires, ['site' => $site]),
            $expires->toFormattedDateString(),
        ));
    }
}

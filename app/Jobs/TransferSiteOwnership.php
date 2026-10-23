<?php

namespace App\Jobs;

use App\Mail\OutreachEmail;
use App\Models\Site;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

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

        $brand = $site->team->brand();
        $exportUrl = route('sites.export', ['current_team' => $site->team->slug, 'site' => $site->id]);

        Mail::to($this->email)->queue(new OutreachEmail(
            __('Your website files for :name', ['name' => $site->lead->name]),
            __("The website for :name has been transferred to you.\n\nDownload the files from the agency, or ask them to send the ZIP export. The live copy on our hosting has been unpublished so you can host it yourself.\n\n:url", [
                'name' => $site->lead->name,
                'url' => $exportUrl,
            ]),
            $brand['name'],
            $brand['email'] ?? '',
        ));
    }
}

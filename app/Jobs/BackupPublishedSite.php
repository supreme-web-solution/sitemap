<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\SiteBackup;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class BackupPublishedSite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const KEEP = 14;

    public int $timeout = 40;

    public int $tries = 1;

    public function __construct(public Site $site)
    {
        $this->onQueue(Queues::MONITORING);
    }

    public function uniqueId(): string
    {
        return 'backup-'.$this->site->id.'-'.now()->format('Ymd');
    }

    /**
     * Store today's published copy so a bad edit can be rolled back from disk.
     */
    public function handle(): void
    {
        $this->site->loadMissing('lead');

        $payload = json_encode([
            'site_id' => $this->site->id,
            'template' => $this->site->template->value,
            'content' => $this->site->content,
            'published_at' => $this->site->published_at?->toISOString(),
            'backed_up_at' => now()->toISOString(),
        ], JSON_UNESCAPED_SLASHES);

        $path = "backups/sites/{$this->site->id}/".now()->format('YmdHis').'.json';

        Storage::disk('local')->put($path, (string) $payload);

        SiteBackup::query()->create([
            'site_id' => $this->site->id,
            'path' => $path,
            'bytes' => strlen((string) $payload),
        ]);

        $keep = $this->site->backups()->latest('id')->limit(self::KEEP)->pluck('id');
        $this->site->backups()->whereNotIn('id', $keep)->each(function (SiteBackup $backup) {
            Storage::disk('local')->delete($backup->path);
            $backup->delete();
        });
    }
}

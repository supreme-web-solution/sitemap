<?php

namespace App\Console\Commands;

use App\Jobs\BackupPublishedSite;
use App\Models\Site;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;

#[Description('Store a JSON snapshot of every published site')]
class BackupPublishedSites extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sites:backup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Site::query()
            ->whereNotNull('published_at')
            ->orderBy('id')
            ->eachById(fn (Site $site) => BackupPublishedSite::dispatch($site), 50);

        return self::SUCCESS;
    }
}

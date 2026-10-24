<?php

namespace App\Console\Commands;

use App\Enums\MailConnectionStatus;
use App\Jobs\SyncTeamMailbox;
use App\Models\MailConnection;
use Illuminate\Console\Command;

class SyncMailInboxes extends Command
{
    protected $signature = 'mail:sync-inboxes';

    protected $description = 'Poll connected team mailboxes for new replies';

    /**
     * Queue a sync job for each mailbox that supports automatic polling.
     */
    public function handle(): int
    {
        MailConnection::query()
            ->whereIn('status', [MailConnectionStatus::Connected, MailConnectionStatus::Pending, MailConnectionStatus::Error])
            ->lazyById()
            ->each(function (MailConnection $connection) {
                if ($connection->syncAvailable()) {
                    SyncTeamMailbox::dispatch($connection);
                }
            });

        return self::SUCCESS;
    }
}

<?php

namespace App\Jobs;

use App\Models\MailConnection;
use App\Services\Mail\InboundMailProcessor;
use App\Services\Mail\MailConnectionManager;
use App\Services\Mail\MailConnectionUnavailableException;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;

class SyncTeamMailbox implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public MailConnection $mailConnection)
    {
        $this->onQueue(Queues::DEFAULT);
    }

    public function uniqueId(): string
    {
        return (string) $this->mailConnection->id;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('mail-inbox-sync')];
    }

    /**
     * Poll the team's mailbox and import new replies.
     */
    public function handle(MailConnectionManager $manager, InboundMailProcessor $processor): void
    {
        $connection = $this->mailConnection->fresh(['team']);

        if ($connection === null || ! $connection->syncAvailable()) {
            return;
        }

        $client = $manager->client($connection);

        try {
            $since = $connection->last_sync_at;

            foreach ($client->fetchSince($connection, $since) as $message) {
                $processor->import($connection, $message);
            }

            $processor->markSyncSuccess($connection);
        } catch (MailConnectionUnavailableException $exception) {
            $processor->markSyncFailure($connection, $exception->getMessage());
        } catch (\Throwable $exception) {
            Log::warning('mail_inbox_sync_failed', [
                'team_id' => $connection->team_id,
                'connection_id' => $connection->id,
            ]);

            $processor->markSyncFailure($connection, __('Could not sync the mailbox. Check your settings and try again.'));
        }
    }
}

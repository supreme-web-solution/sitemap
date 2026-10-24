<?php

namespace App\Services\Mail;

use App\Contracts\Mail\MailConnectionClient;
use App\Models\MailConnection;
use Illuminate\Support\Carbon;

class ImapMailConnectionClient implements MailConnectionClient
{
    public function __construct(private readonly MailboxHostGuard $hostGuard) {}

    /**
     * {@inheritDoc}
     */
    public function verify(MailConnection $connection): void
    {
        if (! config('inbox.imap_sync_enabled')) {
            throw new MailConnectionUnavailableException(
                __('Automatic IMAP sync is not enabled on this server.'),
            );
        }

        $this->withSession($connection, function (): void {
            // Connection + LOGIN + SELECT already ran in connect().
        });
    }

    /**
     * {@inheritDoc}
     */
    public function fetchSince(MailConnection $connection, ?Carbon $since): array
    {
        if (! config('inbox.imap_sync_enabled')) {
            return [];
        }

        $messages = [];

        $this->withSession($connection, function (ImapConnection $session) use ($since, &$messages): void {
            $uids = $session->searchUidsSince($since);

            foreach ($uids as $uid) {
                $fetched = $session->fetchMessage($uid);

                $messages[] = new InboundMailMessage(
                    externalMessageId: $this->normalizeMessageId($fetched['message_id']),
                    fromAddress: $fetched['from'],
                    toAddress: $fetched['to'],
                    subject: $fetched['subject'],
                    snippet: $fetched['snippet'],
                    receivedAt: Carbon::parse($fetched['date']),
                    inReplyTo: $fetched['in_reply_to'] !== null ? $this->normalizeMessageId($fetched['in_reply_to']) : null,
                    references: array_map(fn (string $id) => $this->normalizeMessageId($id), $fetched['references']),
                );
            }
        });

        return $messages;
    }

    /**
     * @param  callable(ImapConnection): void  $callback
     */
    protected function withSession(MailConnection $connection, callable $callback): void
    {
        $credentials = $connection->credentials ?? [];

        $session = new ImapConnection($this->hostGuard);

        try {
            $session->connect($credentials);
            $callback($session);
        } finally {
            $session->disconnect();
        }
    }

    protected function normalizeMessageId(string $messageId): string
    {
        $messageId = trim($messageId);

        if ($messageId === '') {
            return $messageId;
        }

        if (! str_starts_with($messageId, '<')) {
            $messageId = '<'.$messageId.'>';
        }

        return mb_strtolower($messageId);
    }
}

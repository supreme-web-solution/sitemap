<?php

namespace App\Jobs;

use App\Enums\LeadActivityType;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\MessagingAccount;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\ZernioClient;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

class SendLeadMessage implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 45;

    /**
     * Seconds to wait before retrying after an exception.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public Message $message)
    {
        $this->onQueue(Queues::MESSAGING);
    }

    /**
     * Keep each team under its own send rate so one burst cannot flood the provider.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new RateLimited('messaging-send'))->releaseAfter(30)];
    }

    /**
     * Send the message through the team's own Zernio connection.
     */
    public function handle(): void
    {
        $message = $this->message->fresh();

        if ($message === null || $message->status !== MessageStatus::Queued) {
            return;
        }

        $lead = $message->lead;
        $account = MessagingAccount::query()->where('team_id', $message->team_id)->first();

        if ($account === null) {
            $this->markFailed($message, __('Messaging is not connected.'));

            return;
        }

        if ($lead->messaging_opted_out_at !== null || $lead->messaging_consent_at === null) {
            $this->markFailed($message, __('This contact has not agreed to receive messages.'));

            return;
        }

        if ($account->isQuiet()) {
            self::dispatch($message)->delay($account->nextSendableAt());

            return;
        }

        try {
            $result = $this->deliver($message, $account);
        } catch (MessagingException $exception) {
            if ($exception->optedOut) {
                $lead->forceFill(['messaging_opted_out_at' => now()])->save();
                $this->markFailed($message, $exception->getMessage());

                return;
            }

            if ($exception->retryable) {
                throw $exception;
            }

            $this->markFailed($message, $exception->getMessage());

            return;
        }

        $message->forceFill([
            'status' => MessageStatus::Sent,
            'external_id' => $result['id'],
            'external_conversation_id' => $result['conversationId'] ?? $message->external_conversation_id,
            'sent_at' => now(),
            'error' => null,
        ])->save();
    }

    /**
     * Mark the message failed once every attempt is used up.
     */
    public function failed(Throwable $exception): void
    {
        $message = $this->message->fresh();

        if ($message !== null && $message->status === MessageStatus::Queued) {
            $this->markFailed($message, $exception->getMessage());
        }
    }

    /**
     * @return array{id: string|null, conversationId: string|null}
     *
     * @throws MessagingException
     */
    protected function deliver(Message $message, MessagingAccount $account): array
    {
        $client = ZernioClient::for($account);

        if ($message->channel === MessageChannel::Sms) {
            if ($account->sms_from === null) {
                throw new MessagingException(__('No SMS number is set up.'));
            }

            return $client->sendSms($account->sms_from, $message->phone, $message->body, 'message-'.$message->id);
        }

        if ($account->whatsapp_account_id === null) {
            throw new MessagingException(__('No WhatsApp account is set up.'));
        }

        if ($message->template !== null) {
            return $client->openWhatsAppConversation(
                $account->whatsapp_account_id,
                ltrim($message->phone, '+'),
                $message->template['name'],
                $message->template['language'],
                $message->template['params'] ?? [],
            );
        }

        return $client->sendWhatsAppText($account->whatsapp_account_id, (string) $message->external_conversation_id, $message->body);
    }

    protected function markFailed(Message $message, string $reason): void
    {
        $message->forceFill(['status' => MessageStatus::Failed, 'error' => $reason])->save();

        $message->lead->recordActivity(
            LeadActivityType::MessageSent,
            __(':channel message could not be sent: :reason', ['channel' => $message->channel->label(), 'reason' => $reason]),
            ['message_id' => $message->id],
        );
    }
}

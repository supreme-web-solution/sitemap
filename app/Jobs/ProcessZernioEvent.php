<?php

namespace App\Jobs;

use App\Enums\LeadActivityType;
use App\Enums\MessageChannel;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Message;
use App\Support\PhoneNumber;
use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ProcessZernioEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Replies that mean the contact no longer wants messages.
     *
     * @var list<string>
     */
    public const STOP_WORDS = ['stop', 'stopall', 'unsubscribe', 'cancel', 'end', 'quit'];

    /**
     * Replies that mean the contact wants messages again.
     *
     * @var list<string>
     */
    public const START_WORDS = ['start', 'unstop', 'subscribe'];

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public int $teamId, public array $payload)
    {
        $this->onQueue(Queues::MESSAGING);
    }

    /**
     * Apply one webhook event: store a reply, honour STOP, or update delivery status.
     */
    public function handle(): void
    {
        $event = strtolower((string) ($this->payload['event'] ?? $this->payload['type'] ?? ''));
        $data = (array) ($this->payload['data'] ?? $this->payload['message'] ?? $this->payload);

        match (true) {
            str_contains($event, 'received'), str_contains($event, 'inbound') => $this->storeReply($data),
            str_contains($event, 'delivered') => $this->updateStatus($data, MessageStatus::Delivered),
            str_contains($event, 'failed'), str_contains($event, 'undelivered') => $this->updateStatus($data, MessageStatus::Failed),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeReply(array $data): void
    {
        $phone = (string) ($data['from'] ?? $data['participantId'] ?? $data['sender']['id'] ?? $data['sender'] ?? '');
        $text = trim((string) ($data['text'] ?? $data['message'] ?? $data['body'] ?? ''));
        $externalId = (string) ($data['messageId'] ?? $data['id'] ?? '');
        $conversationId = (string) ($data['conversationId'] ?? '');
        $channel = $this->channelFor($data);

        $lead = $this->findLead($phone, $conversationId);

        if ($lead === null) {
            return;
        }

        if ($externalId !== '' && Message::query()->where('channel', $channel)->where('external_id', $externalId)->exists()) {
            return;
        }

        Message::query()->create([
            'team_id' => $this->teamId,
            'lead_id' => $lead->id,
            'channel' => $channel,
            'direction' => Message::INBOUND,
            'status' => MessageStatus::Received,
            'body' => $text,
            'phone' => PhoneNumber::e164($phone) ?? $phone,
            'external_id' => $externalId !== '' ? $externalId : null,
            'external_conversation_id' => $conversationId !== '' ? $conversationId : null,
            'sent_at' => now(),
        ]);

        $lead->recordActivity(LeadActivityType::MessageReceived, __(':channel reply: :text', ['channel' => $channel->label(), 'text' => Str::limit($text, 120)]));

        $word = strtolower(trim($text, " \t\n\r\0\x0B.!"));

        if (in_array($word, self::STOP_WORDS, true)) {
            $lead->forceFill(['messaging_opted_out_at' => now()])->save();
        } elseif (in_array($word, self::START_WORDS, true)) {
            $lead->forceFill(['messaging_opted_out_at' => null, 'messaging_consent_at' => now()])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function updateStatus(array $data, MessageStatus $status): void
    {
        $externalId = (string) ($data['messageId'] ?? $data['id'] ?? '');

        if ($externalId === '') {
            return;
        }

        $message = Message::query()
            ->where('team_id', $this->teamId)
            ->where('direction', Message::OUTBOUND)
            ->where('external_id', $externalId)
            ->first();

        if ($message === null || $message->status === MessageStatus::Delivered) {
            return;
        }

        $message->forceFill([
            'status' => $status,
            'error' => $status === MessageStatus::Failed ? (string) ($data['error'] ?? $data['reason'] ?? __('Delivery failed.')) : null,
        ])->save();
    }

    protected function findLead(string $phone, string $conversationId): ?Lead
    {
        if ($conversationId !== '') {
            $leadId = Message::query()->where('team_id', $this->teamId)->where('external_conversation_id', $conversationId)->value('lead_id');

            if ($leadId !== null) {
                return Lead::query()->where('team_id', $this->teamId)->find($leadId);
            }
        }

        $e164 = PhoneNumber::e164($phone);

        return $e164 === null
            ? null
            : Lead::query()->where('team_id', $this->teamId)->where('phone_e164', $e164)->orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function channelFor(array $data): MessageChannel
    {
        $platform = strtolower((string) ($data['platform'] ?? $data['channel'] ?? ''));

        return str_contains($platform, 'whatsapp') ? MessageChannel::WhatsApp : MessageChannel::Sms;
    }
}

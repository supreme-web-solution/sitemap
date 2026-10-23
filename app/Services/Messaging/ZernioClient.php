<?php

namespace App\Services\Messaging;

use App\Models\MessagingAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ZernioClient
{
    public function __construct(private readonly string $apiKey) {}

    public static function for(MessagingAccount $account): self
    {
        return new self($account->api_key);
    }

    /**
     * Check the API key works.
     *
     * @throws MessagingException
     */
    public function verify(): void
    {
        $this->check($this->send(fn () => $this->http()->get('/accounts')));
    }

    /**
     * Send an SMS. The idempotency key makes a retried job safe: Zernio replays the first result instead of texting twice.
     *
     * @return array{id: string|null, conversationId: string|null}
     *
     * @throws MessagingException
     */
    public function sendSms(string $from, string $to, string $text, string $idempotencyKey): array
    {
        $response = $this->send(fn () => $this->http()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/sms/messages', ['from' => $from, 'to' => $to, 'text' => $text]));

        $this->check($response);

        return ['id' => $response->json('id'), 'conversationId' => $response->json('conversationId')];
    }

    /**
     * Open a WhatsApp conversation with an approved template.
     *
     * @param  list<string>  $params
     * @return array{id: string|null, conversationId: string|null}
     *
     * @throws MessagingException
     */
    public function openWhatsAppConversation(string $accountId, string $participantDigits, string $template, string $language, array $params): array
    {
        $response = $this->send(fn () => $this->http()->post('/inbox/conversations', [
            'accountId' => $accountId,
            'participantId' => $participantDigits,
            'templateName' => $template,
            'templateLanguage' => $language,
            'templateParams' => array_values($params),
        ]));

        $this->check($response);

        return ['id' => $response->json('data.messageId'), 'conversationId' => $response->json('data.conversationId')];
    }

    /**
     * Send free text inside a WhatsApp conversation that is already open (within 24 hours of the customer's last reply).
     *
     * @return array{id: string|null, conversationId: string|null}
     *
     * @throws MessagingException
     */
    public function sendWhatsAppText(string $accountId, string $conversationId, string $text): array
    {
        $response = $this->send(fn () => $this->http()->post("/inbox/conversations/{$conversationId}/messages", [
            'accountId' => $accountId,
            'message' => $text,
        ]));

        $this->check($response);

        return ['id' => $response->json('data.messageId') ?? $response->json('data.id') ?? $response->json('id'), 'conversationId' => $conversationId];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.zernio.base_url'))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    /**
     * @param  callable(): Response  $request
     *
     * @throws MessagingException
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new MessagingException(__('The messaging service could not be reached.'), retryable: true);
        }
    }

    /**
     * @throws MessagingException
     */
    private function check(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $detail = (string) ($response->json('error') ?? $response->json('message') ?? 'Request failed');

        throw match (true) {
            $response->status() === 409 && str_contains(strtolower($detail), 'opt') => new MessagingException(__('This number opted out of messages.'), optedOut: true),
            $response->status() === 401 => new MessagingException(__('The messaging API key was rejected.')),
            $response->status() === 429, $response->serverError() => new MessagingException($detail, retryable: true),
            default => new MessagingException($detail),
        };
    }
}

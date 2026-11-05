<?php

namespace App\Services\Messaging;

use App\Models\MessagingAccount;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ZernioClient
{
    public function __construct(private readonly string $apiKey) {}

    public static function platformKey(): ?string
    {
        $key = config('services.zernio.api_key');

        return filled($key) ? (string) $key : null;
    }

    public static function usesPlatformKey(): bool
    {
        return self::platformKey() !== null;
    }

    /**
     * @throws MessagingException
     */
    public static function platform(): self
    {
        $key = self::platformKey();

        if ($key === null) {
            throw new MessagingException(__('Messaging is not configured on the server.'));
        }

        return new self($key);
    }

    /**
     * @throws MessagingException
     */
    public static function for(MessagingAccount $account): self
    {
        if (filled($account->api_key)) {
            return new self($account->api_key);
        }

        return self::platform();
    }

    /**
     * Check the API key works.
     *
     * @throws MessagingException
     */
    public function verify(): void
    {
        $this->listAccountRows();
    }

    /**
     * Map one business number to SMS sending and WhatsApp (when the provider links them).
     *
     * @return array{sms_from: string, whatsapp_account_id: string|null}
     *
     * @throws MessagingException
     */
    public function linkSendingNumber(string $e164): array
    {
        $whatsappAccountId = null;

        foreach ($this->listAccountRows() as $row) {
            if (! is_array($row) || ! $this->accountRowMatchesPhone($row, $e164)) {
                continue;
            }

            if ($this->accountRowSupportsWhatsApp($row)) {
                $id = $row['id'] ?? $row['accountId'] ?? $row['account_id'] ?? null;

                if (filled($id)) {
                    $whatsappAccountId = (string) $id;
                }
            }
        }

        return [
            'sms_from' => $e164,
            'whatsapp_account_id' => $whatsappAccountId,
        ];
    }

    /**
     * @return list<mixed>
     *
     * @throws MessagingException
     */
    private function listAccountRows(): array
    {
        $response = $this->send(fn () => $this->http()->get('/accounts'));
        $this->check($response);

        $payload = $response->json();

        if (is_array($payload) && isset($payload['accounts']) && is_array($payload['accounts'])) {
            return $payload['accounts'];
        }

        if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
            return $payload['data'];
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function accountRowMatchesPhone(array $row, string $e164): bool
    {
        $candidates = [
            $row['phone'] ?? null,
            $row['phoneNumber'] ?? null,
            $row['from'] ?? null,
            $row['sender'] ?? null,
            $row['e164'] ?? null,
        ];

        $target = PhoneNumber::digits($e164);

        if ($target === null) {
            return false;
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            if (PhoneNumber::digits($candidate) === $target) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function accountRowSupportsWhatsApp(array $row): bool
    {
        $channel = strtolower((string) ($row['channel'] ?? $row['type'] ?? ''));

        if (str_contains($channel, 'whatsapp') || $channel === 'wa') {
            return true;
        }

        $channels = $row['channels'] ?? $row['capabilities'] ?? null;

        if (is_array($channels)) {
            foreach ($channels as $item) {
                $label = strtolower((string) $item);

                if (str_contains($label, 'whatsapp') || $label === 'wa') {
                    return true;
                }
            }
        }

        return (bool) ($row['whatsapp'] ?? $row['supportsWhatsApp'] ?? false);
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

    /**
     * Show "typing…" in WhatsApp while the Command Center agent is working.
     *
     * @throws MessagingException
     */
    public function sendWhatsAppTyping(string $accountId, string $conversationId): void
    {
        $response = $this->send(fn () => $this->http()->post("/inbox/conversations/{$conversationId}/typing", [
            'accountId' => $accountId,
        ]));

        if (! $response->successful() && ! in_array($response->status(), [404, 405], true)) {
            $this->check($response);
        }
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

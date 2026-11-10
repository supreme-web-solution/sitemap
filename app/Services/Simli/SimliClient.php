<?php

namespace App\Services\Simli;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Simli speech-to-video API: mint short-lived session tokens for the browser widget.
 *
 * @see https://docs.simli.com/api-reference/compose-session-token
 */
class SimliClient
{
    public function configured(): bool
    {
        return filled(config('services.simli.api_key'));
    }

    /**
     * Create a temporary session token the widget can use without seeing our API key.
     *
     * @return array{session_token: string, ice_servers: list<array<string, mixed>>}
     */
    public function session(string $faceId): array
    {
        if (! $this->configured()) {
            throw new RuntimeException(__('Add SIMLI_API_KEY to enable the talking avatar.'));
        }

        try {
            $tokenResponse = $this->http()
                ->post('/compose/token', [
                    'faceId' => $faceId,
                    'apiVersion' => 'v2',
                    'handleSilence' => true,
                    'maxSessionLength' => (int) config('assistant.talking_avatar_max_session_seconds', 600),
                    'maxIdleTime' => (int) config('assistant.talking_avatar_max_idle_seconds', 180),
                    'audioInputFormat' => 'pcm16',
                    'streaming_width' => 768,
                    'streaming_height' => 768,
                ])
                ->throw();

            $iceResponse = $this->http()->get('/compose/ice');
        } catch (RequestException $exception) {
            Log::warning('Simli session token failed.', [
                'status' => $exception->response->status(),
                'body' => $exception->response->json() ?? $exception->response->body(),
            ]);

            throw new RuntimeException($this->errorMessage($exception), previous: $exception);
        } catch (ConnectionException $exception) {
            Log::warning('Simli connection failed.', ['message' => $exception->getMessage()]);

            throw new RuntimeException(__('Could not reach Simli.'), previous: $exception);
        }

        $token = (string) ($tokenResponse->json('session_token') ?? '');

        if ($token === '' || str_starts_with($token, 'FAIL')) {
            throw new RuntimeException((string) ($tokenResponse->json('detail') ?? __('Simli did not start a session.')));
        }

        $iceServers = $iceResponse->successful()
            ? ($iceResponse->json() ?? [])
            : [['urls' => ['stun:stun.l.google.com:19302']]];

        return [
            'session_token' => $token,
            'ice_servers' => is_array($iceServers) ? array_values($iceServers) : [],
        ];
    }

    protected function http()
    {
        return Http::baseUrl(rtrim((string) config('services.simli.base_url'), '/'))
            ->withHeaders(['x-simli-api-key' => (string) config('services.simli.api_key')])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20);
    }

    protected function errorMessage(RequestException $exception): string
    {
        $status = $exception->response->status();
        $detail = (string) ($exception->response->json('detail')
            ?? $exception->response->json('message')
            ?? '');

        return match (true) {
            $status === 401, $status === 403 => __('Simli rejected the API key. Check SIMLI_API_KEY.'),
            $status === 402 => __('Your Simli account is out of minutes.'),
            $status === 429 => __('Simli is busy right now. Try again in a minute.'),
            $detail !== '' => $detail,
            default => __('Simli returned an error (:status).', ['status' => $status]),
        };
    }
}

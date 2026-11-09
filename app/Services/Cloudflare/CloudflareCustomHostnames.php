<?php

namespace App\Services\Cloudflare;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cloudflare for SaaS Custom Hostnames API client.
 *
 * @see https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/
 */
class CloudflareCustomHostnames
{
    /**
     * Whether SaaS hostname provisioning is configured.
     */
    public function enabled(): bool
    {
        return filled(config('services.cloudflare.api_token'))
            && filled(config('services.cloudflare.zone_id'));
    }

    /**
     * Register a customer hostname and request an SSL certificate (HTTP DCV).
     *
     * @return array{id: string, status: string|null, ssl_status: string|null, ownership_txt_name: string|null, ownership_txt_value: string|null}
     */
    public function create(string $hostname): array
    {
        try {
            $response = $this->client()->post($this->basePath(), $this->createPayload($hostname))->throw()->json('result');
        } catch (RequestException $exception) {
            if ($exception->response->status() === 409) {
                $existing = $this->findByHostname($hostname);

                if ($existing !== null) {
                    return $existing;
                }
            }

            throw new RuntimeException($this->errorMessage($exception), previous: $exception);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach Cloudflare.', previous: $exception);
        }

        return $this->normalize($response ?? []);
    }

    /**
     * Refresh a custom hostname's activation and SSL status.
     *
     * @return array{id: string, status: string|null, ssl_status: string|null, ownership_txt_name: string|null, ownership_txt_value: string|null}
     */
    public function get(string $id): array
    {
        try {
            $response = $this->client()->get($this->basePath().'/'.$id)->throw()->json('result');
        } catch (RequestException $exception) {
            throw new RuntimeException($this->errorMessage($exception), previous: $exception);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach Cloudflare.', previous: $exception);
        }

        return $this->normalize($response ?? []);
    }

    /**
     * Find an existing custom hostname by DNS name.
     *
     * @return array{id: string, status: string|null, ssl_status: string|null, ownership_txt_name: string|null, ownership_txt_value: string|null}|null
     */
    public function findByHostname(string $hostname): ?array
    {
        try {
            $results = $this->client()->get($this->basePath(), [
                'hostname' => strtolower($hostname),
            ])->throw()->json('result');
        } catch (RequestException|ConnectionException) {
            return null;
        }

        if (! is_array($results) || $results === []) {
            return null;
        }

        return $this->normalize($results[0]);
    }

    /**
     * Remove a custom hostname from Cloudflare.
     */
    public function delete(string $id): void
    {
        try {
            $this->client()->delete($this->basePath().'/'.$id)->throw();
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return;
            }

            throw new RuntimeException($this->errorMessage($exception), previous: $exception);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Could not reach Cloudflare.', previous: $exception);
        }
    }

    /**
     * Point Cloudflare at our Forge origin hostname for the TLS handshake (avoids 525).
     *
     * @return array<string, mixed>
     */
    protected function createPayload(string $hostname): array
    {
        $payload = [
            'hostname' => strtolower($hostname),
            'ssl' => [
                'method' => 'http',
                'type' => 'dv',
                'settings' => [
                    'http2' => 'on',
                    'tls_1_3' => 'on',
                    'min_tls_version' => '1.2',
                ],
            ],
        ];

        $origin = strtolower((string) config('services.cloudflare.origin_hostname'));

        if ($origin !== '') {
            $payload['custom_origin_server'] = $origin;
            $payload['custom_origin_sni'] = $origin;
        }

        return $payload;
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl('https://api.cloudflare.com/client/v4')
            ->withToken((string) config('services.cloudflare.api_token'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(3, 200);
    }

    protected function basePath(): string
    {
        return '/zones/'.config('services.cloudflare.zone_id').'/custom_hostnames';
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{id: string, status: string|null, ssl_status: string|null, ownership_txt_name: string|null, ownership_txt_value: string|null}
     */
    protected function normalize(array $result): array
    {
        $ownership = is_array($result['ownership_verification'] ?? null)
            ? $result['ownership_verification']
            : [];

        $ssl = is_array($result['ssl'] ?? null) ? $result['ssl'] : [];

        return [
            'id' => (string) ($result['id'] ?? ''),
            'status' => isset($result['status']) ? (string) $result['status'] : null,
            'ssl_status' => isset($ssl['status']) ? (string) $ssl['status'] : null,
            'ownership_txt_name' => isset($ownership['name']) ? (string) $ownership['name'] : null,
            'ownership_txt_value' => isset($ownership['value']) ? (string) $ownership['value'] : null,
        ];
    }

    protected function errorMessage(RequestException $exception): string
    {
        $errors = $exception->response->json('errors');

        if (is_array($errors) && isset($errors[0]['message'])) {
            return (string) $errors[0]['message'];
        }

        return 'Cloudflare rejected the hostname request.';
    }
}

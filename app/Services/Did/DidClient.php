<?php

namespace App\Services\Did;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * D-ID API client: stock presenters, text-to-speech voices and presenter videos (clips).
 *
 * @see https://docs.d-id.com/reference/get-started
 *
 * @phpstan-type Presenter array{id: string, name: string, gender: string|null, thumbnail_url: string|null}
 * @phpstan-type Voice array{id: string, name: string, gender: string|null, locale: string, provider: string, preview_url: string|null}
 * @phpstan-type Clip array{id: string, status: string, result_url: string|null, error: string|null}
 */
class DidClient
{
    /**
     * Presenter and voice lists change rarely, so they are cached.
     */
    private const LIST_TTL_HOURS = 6;

    /**
     * Most presenters returned to the editor; D-ID lists many outfits per person.
     */
    private const MAX_PRESENTERS = 60;

    /**
     * Whether the D-ID API key is set.
     */
    public function configured(): bool
    {
        return filled(config('services.did.api_key'));
    }

    /**
     * Stock presenters that can be used with clips.
     *
     * @return list<Presenter>
     */
    public function presenters(): array
    {
        return Cache::remember('did.presenters', now()->addHours(self::LIST_TTL_HOURS), function (): array {
            $rows = $this->get('/clips/presenters')['presenters'] ?? [];

            return collect($rows)
                ->filter(fn ($row) => is_array($row)
                    && filled($row['presenter_id'] ?? null)
                    && filled($row['thumbnail_url'] ?? null)
                    && ! ($row['is_greenscreen'] ?? false))
                ->map(fn (array $row): array => [
                    'id' => (string) $row['presenter_id'],
                    'name' => $this->presenterName($row),
                    'gender' => $this->normalizeGender($row['gender'] ?? null),
                    'thumbnail_url' => (string) $row['thumbnail_url'],
                ])
                ->unique('name')
                ->take(self::MAX_PRESENTERS)
                ->values()
                ->all();
        });
    }

    /**
     * English text-to-speech voices, with a sample clip when D-ID provides one.
     *
     * @return list<Voice>
     */
    public function voices(): array
    {
        return Cache::remember('did.voices', now()->addHours(self::LIST_TTL_HOURS), function (): array {
            $payload = $this->get('/voices', ['provider' => 'microsoft']);
            $rows = array_is_list($payload) ? $payload : ($payload['voices'] ?? []);

            return collect($rows)
                ->filter(fn ($row) => is_array($row)
                    && filled($row['id'] ?? null)
                    && ($row['access'] ?? 'public') === 'public'
                    && $this->voiceLocale($row) !== null
                    && str_starts_with(strtolower((string) $this->voiceLocale($row)), 'en'))
                ->map(fn (array $row): array => [
                    'id' => (string) $row['id'],
                    'name' => trim((string) ($row['name'] ?? $row['id'])),
                    'gender' => $this->normalizeGender($row['gender'] ?? null),
                    'locale' => (string) $this->voiceLocale($row),
                    'provider' => (string) ($row['provider'] ?? 'microsoft'),
                    'preview_url' => filled($row['preview_url'] ?? null) ? (string) $row['preview_url'] : null,
                ])
                ->sortBy([['locale', 'asc'], ['name', 'asc']])
                ->values()
                ->all();
        });
    }

    /**
     * Pick the best voice for a presenter's gender, so a male presenter gets a male voice.
     *
     * @param  list<Voice>  $voices
     * @return Voice|null
     */
    public function voiceForGender(?string $gender, array $voices): ?array
    {
        $matching = array_values(array_filter(
            $voices,
            fn (array $voice) => $gender === null || $voice['gender'] === $gender,
        ));

        $preferred = $gender === 'male' ? 'en-US-GuyNeural' : 'en-US-JennyNeural';

        foreach ($matching as $voice) {
            if ($voice['id'] === $preferred) {
                return $voice;
            }
        }

        foreach ($matching as $voice) {
            if ($voice['locale'] === 'en-US') {
                return $voice;
            }
        }

        return $matching[0] ?? null;
    }

    /**
     * Start rendering a presenter video and return D-ID's clip id.
     *
     * @param  array{id: string, provider: string}  $voice
     */
    public function createClip(string $presenterId, string $script, array $voice): string
    {
        $payload = $this->send('post', '/clips', [
            'presenter_id' => $presenterId,
            'script' => [
                'type' => 'text',
                'input' => $script,
                'provider' => [
                    'type' => $voice['provider'],
                    'voice_id' => $voice['id'],
                ],
            ],
            'config' => ['result_format' => 'mp4'],
        ]);

        $id = (string) ($payload['id'] ?? '');

        if ($id === '') {
            throw new RuntimeException(__('D-ID did not start the video. Try again in a moment.'));
        }

        return $id;
    }

    /**
     * Get the current state of a clip.
     *
     * @return Clip
     */
    public function clip(string $id): array
    {
        $payload = $this->get('/clips/'.$id);

        $error = $payload['error'] ?? null;

        return [
            'id' => $id,
            'status' => (string) ($payload['status'] ?? 'created'),
            'result_url' => filled($payload['result_url'] ?? null) ? (string) $payload['result_url'] : null,
            'error' => is_array($error)
                ? (string) ($error['description'] ?? $error['kind'] ?? 'D-ID could not render this video.')
                : (is_string($error) && $error !== '' ? $error : null),
        ];
    }

    /**
     * Download a finished video; D-ID result links expire, so the file is stored by us.
     */
    public function download(string $url): string
    {
        try {
            return Http::timeout(120)->get($url)->throw()->body();
        } catch (RequestException|ConnectionException $exception) {
            throw new RuntimeException(__('The finished video could not be downloaded from D-ID.'), previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, $query);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    private function send(string $method, string $path, array $data = []): array
    {
        if (! $this->configured()) {
            throw new RuntimeException(__('Add DID_API_KEY in your environment to create videos.'));
        }

        try {
            $response = $this->client()->{$method}($path, $data)->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException($this->errorMessage($exception), previous: $exception);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(__('Could not reach D-ID.'), previous: $exception);
        }

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.did.base_url'), '/'))
            ->withHeaders(['Authorization' => 'Basic '.config('services.did.api_key')])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(30);
    }

    private function errorMessage(RequestException $exception): string
    {
        $status = $exception->response->status();

        return match (true) {
            $status === 401 => __('D-ID rejected the API key. Check DID_API_KEY.'),
            $status === 402 => __('Your D-ID account is out of credits.'),
            $status === 403 => __('D-ID declined this video. The script or presenter was not allowed.'),
            $status === 429 => __('D-ID is busy right now. Try again in a minute.'),
            default => (string) ($exception->response->json('description')
                ?? $exception->response->json('message')
                ?? __('D-ID returned an error (:status).', ['status' => $status])),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function presenterName(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        $id = preg_replace('/^v\d+_public_/i', '', (string) $row['presenter_id']) ?? (string) $row['presenter_id'];

        return ucfirst(strtolower(preg_split('/[_@]/', $id)[0] ?? $id));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function voiceLocale(array $row): ?string
    {
        $languages = $row['languages'] ?? [];

        if (is_array($languages) && is_array($languages[0] ?? null) && filled($languages[0]['locale'] ?? null)) {
            return (string) $languages[0]['locale'];
        }

        return filled($row['locale'] ?? null) ? (string) $row['locale'] : null;
    }

    private function normalizeGender(mixed $gender): ?string
    {
        $gender = strtolower(trim((string) $gender));

        return in_array($gender, ['male', 'female'], true) ? $gender : null;
    }
}

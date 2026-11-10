<?php

namespace App\Services\PitchVideos;

use App\Services\Ai\AiAvailability;
use App\Services\Did\DidClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;
use Throwable;

/**
 * Short spoken samples for D-ID Microsoft voices in the sales-video settings.
 *
 * D-ID does not return preview URLs for these voices, so we generate a cached
 * OpenAI sample that matches gender (and hints at the locale) for the picker.
 */
class PitchVoicePreview
{
    public function __construct(protected DidClient $did) {}

    public function available(): bool
    {
        return AiAvailability::openaiConfigured();
    }

    /**
     * Public URL of a short sample for this voice, or null when unavailable.
     */
    public function url(string $voiceId): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $voice = collect($this->did->voices())->firstWhere('id', $voiceId);

        if ($voice === null) {
            return null;
        }

        $path = 'assistant/voice-previews/pitch/'.preg_replace('/[^A-Za-z0-9._-]/', '_', $voiceId).'.mp3';
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            $lock = Cache::lock('pitch-voice-preview:'.$voiceId, 20);

            if ($lock->get()) {
                try {
                    if (! $disk->exists($path)) {
                        $spoken = $this->generate($voice);

                        if ($spoken === null) {
                            return null;
                        }

                        $disk->put($path, base64_decode($spoken));
                    }
                } finally {
                    $lock->release();
                }
            }
        }

        return $disk->exists($path) ? $disk->url($path) : null;
    }

    /**
     * @param  array{id: string, name: string, gender: string|null, locale: string, provider: string, preview_url: string|null}  $voice
     */
    protected function generate(array $voice): ?string
    {
        $openaiVoice = $voice['gender'] === 'male' ? 'ash' : 'nova';
        $locale = $voice['locale'] !== '' ? $voice['locale'] : 'en-US';
        $instructions = "Speak clearly as a {$voice['gender']} sales presenter. Use a natural {$locale} accent. Warm and professional.";

        try {
            $response = Audio::of('Hi, I am your sales video presenter. Ask me to introduce this website.')
                ->voice($openaiVoice)
                ->instructions($instructions)
                ->timeout(20)
                ->generate();
        } catch (Throwable $exception) {
            Log::warning('Pitch voice preview failed.', [
                'voice_id' => $voice['id'],
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        return $response->audio;
    }
}

<?php

namespace App\Services\Assistant;

use App\Services\Ai\AiAvailability;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Audio;
use Throwable;

/**
 * Turns the assistant's written reply into spoken audio with a natural AI voice.
 */
class AssistantSpeech
{
    /**
     * The longest text we ask the voice model to read in one go.
     */
    public const MAX_LENGTH = 900;

    public function __construct(protected AssistantVoices $voices) {}

    public function available(): bool
    {
        return AiAvailability::openaiConfigured();
    }

    /**
     * Speak a reply. Returns null when voice is off, unavailable, or generation fails.
     *
     * @return array{mime: string, base64: string}|null
     */
    public function speak(string $text, string $voiceId): ?array
    {
        if (! $this->voices->isSpoken($voiceId) || ! $this->available()) {
            return null;
        }

        $text = Str::limit(trim($text), self::MAX_LENGTH, '');

        if ($text === '') {
            return null;
        }

        try {
            $response = Audio::of($text)->voice($voiceId)->timeout(20)->generate();
        } catch (Throwable $exception) {
            Log::warning('Website assistant speech failed.', ['message' => $exception->getMessage()]);

            return null;
        }

        return [
            'mime' => $response->mimeType() ?: 'audio/mpeg',
            'base64' => $response->audio,
        ];
    }

    /**
     * A short sample of a voice for the settings panel, cached on the public disk.
     */
    public function previewUrl(string $voiceId): ?string
    {
        if (! $this->voices->isSpoken($voiceId) || ! $this->available()) {
            return null;
        }

        $path = 'assistant/voice-previews/'.$voiceId.'.mp3';
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            $lock = Cache::lock('assistant-voice-preview:'.$voiceId, 20);

            if ($lock->get()) {
                try {
                    if (! $disk->exists($path)) {
                        $spoken = $this->speak(
                            'Hi, I am your website assistant. Ask me anything about the business.',
                            $voiceId,
                        );

                        if ($spoken === null) {
                            return null;
                        }

                        $disk->put($path, base64_decode($spoken['base64']));
                    }
                } finally {
                    $lock->release();
                }
            }
        }

        return $disk->exists($path) ? $disk->url($path) : null;
    }
}

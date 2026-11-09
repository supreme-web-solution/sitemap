<?php

namespace App\Services\PitchVideos;

use App\Ai\Agents\PitchVideoScriptWriter;
use App\Models\Site;
use App\Services\Ai\AiAvailability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Writes the spoken script for a website's sales video, tailored to the business.
 */
class PitchScriptGenerator
{
    /**
     * The longest script D-ID is asked to read, in characters (roughly 60 seconds of speech).
     */
    public const MAX_LENGTH = 1000;

    public function available(): bool
    {
        return AiAvailability::anthropicConfigured();
    }

    /**
     * @throws ValidationException
     */
    public function generate(Site $site): string
    {
        if (! $this->available()) {
            throw ValidationException::withMessages([
                'script' => __('Add ANTHROPIC_API_KEY in your environment to write the script with AI.'),
            ]);
        }

        try {
            $response = (new PitchVideoScriptWriter($this->systemPrompt()))
                ->prompt($this->userPrompt($site));

            $script = trim((string) ($response['script'] ?? ''));
        } catch (Throwable $exception) {
            Log::warning('Pitch video script generation failed.', ['message' => $exception->getMessage()]);

            throw ValidationException::withMessages([
                'script' => __('The AI service is unavailable right now. Try again in a moment.'),
            ]);
        }

        $script = trim((string) preg_replace('#https?://\S+#i', '', $script));

        if ($script === '') {
            throw ValidationException::withMessages([
                'script' => __('The AI did not return a usable script. Try again.'),
            ]);
        }

        return Str::limit($script, self::MAX_LENGTH, '');
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You write the spoken script for a short sales video. An AI presenter reads it to the owner of a local business to introduce a free website that was built for them.

Return JSON only with:
- script: what the presenter says, 80 to 120 words (about 40 seconds), plain spoken sentences.

Structure:
1. Greet the business by name and say who the message is from (the agency).
2. Say a website has already been built for them, and name one or two specific things it features (services, headline, or location).
3. If the business already has a website, mention one real weakness from the audit notes in a polite, constructive way and say the new site fixes it. If it has no website, say customers searching in their town cannot find them online yet and this gives them a home on the web.
4. Close by telling them to open the link in the email to see the website, and that replying to the email is the easiest way to get it live.

Rules:
- Speak directly to the owner. Warm, confident, human, not salesy or pushy.
- Use only the facts provided. Never invent statistics, awards, prices, guarantees, or customer counts.
- No URLs, no emojis, no markdown, no stage directions, no sound effects, no placeholders.
- Write numbers and ratings the way they are spoken.
- Do not mention AI, avatars, or that the presenter is not a real person.
PROMPT;
    }

    protected function userPrompt(Site $site): string
    {
        $site->loadMissing('lead', 'team');
        $lead = $site->lead;
        $content = $site->content;

        $lines = [
            'Agency: '.$site->team->brand()['name'],
            'Business: '.$lead->name,
            'Category: '.($lead->category ?: 'local business'),
            'City: '.($lead->city ?: 'unknown'),
        ];

        if ($lead->rating !== null && (float) $lead->rating >= 4.0 && $lead->reviews_count > 0) {
            $lines[] = "Google rating: {$lead->rating} from {$lead->reviews_count} reviews";
        }

        $lines[] = 'Website headline: '.($content['headline'] ?? '');
        $lines[] = 'Website tagline: '.($content['tagline'] ?? '');

        $services = collect($content['services'] ?? [])->pluck('title')->filter()->take(4)->implode(', ');

        if ($services !== '') {
            $lines[] = "Services shown on the website: {$services}";
        }

        $lines = [...$lines, ...$this->talkingPoints($site)];
        $lines[] = 'Write the script.';

        return implode("\n", $lines);
    }

    /**
     * Facts that shape what the presenter pitches.
     *
     * Further talking points can be added here later without changing the rest of the script flow.
     *
     * @return list<string>
     */
    protected function talkingPoints(Site $site): array
    {
        $lead = $site->lead;

        if (blank($lead->website)) {
            return ['Current website: none. The business has no website yet.'];
        }

        $issues = collect($lead->website_audit['checks'] ?? [])
            ->where('status', 'fail')
            ->pluck('detail')
            ->filter()
            ->take(3)
            ->all();

        $points = ['Current website: they already have one, so pitch the new website as an upgrade.'];

        foreach ($issues as $issue) {
            $points[] = 'Audit note: '.$issue;
        }

        if ($issues === []) {
            $points[] = 'Audit note: none recorded. Keep the comparison general and do not invent problems.';
        }

        return $points;
    }
}

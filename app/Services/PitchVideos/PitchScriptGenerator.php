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
        $script = $this->stripFreeClaims($script);

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
You write the spoken script for a short sales video. An AI presenter reads it to the owner of a local business. The agency already built a custom website preview for them. The preview is free to look at; launching and keeping the site live is a paid service. Never say the website itself is free, complimentary, no-cost, or a gift.

Return JSON only with:
- script: what the presenter says, 90 to 130 words (about 45 seconds), plain spoken sentences.

Goal: make the owner curious enough to open the preview and reply. Sound like a capable local agency partner, not a cold sales call.

Structure:
1. Greet them by first name or business name and say who the message is from (the agency).
2. Lead with a concrete reason this matters for their business in their town (calls, trust, looking professional, winning jobs from people who search).
3. Tell them a custom website preview is already built for them. Name one or two specific details from the preview (headline, services, or city).
4. Then use the website status notes:
   - If they have no website: say their Google listing does not show a website of their own yet. Do NOT say customers cannot find them at all — they may still appear on Google Maps. Explain that a real website makes it easier to get contacted, show services, and look more established than a listing alone.
   - If they already have a website: politely name one real weakness from the audit notes (or keep the comparison general if none are listed) and say the new preview is built to fix that and make contacting them easier.
5. Close with a clear next step: open the link in the email to see the preview, and reply if they want it live. Do not invent a price, discount, or deadline.

Rules:
- Speak directly to the owner. Warm, confident, specific, and convincing. Short sentences. No filler like "pretty cool" or "something special".
- Use only the facts provided. Never invent statistics, awards, prices, guarantees, reviews counts beyond what is given, or customer numbers.
- Never say free, complimentary, no charge, on the house, or gift. Prefer "preview", "already built for you to review", or "ready for you to look at".
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
            'Pricing: launching and keeping the website live is paid. The preview is free to review only. Never call the website free.',
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

        // Only treat as "no website" when the Google/Maps listing has no website URL.
        if (blank($lead->website)) {
            return [
                'Website status: no website on their listing. Pitch owning a real website, not "being found online".',
                'Do not claim customers cannot find them — they may still appear on Google Maps.',
                'Angle: a listing alone makes it harder to show services, build trust, and get contacted. This preview gives them a proper site ready to review.',
            ];
        }

        $issues = collect($lead->website_audit['checks'] ?? [])
            ->where('status', 'fail')
            ->pluck('detail')
            ->filter()
            ->take(3)
            ->all();

        $points = [
            'Website status: they already have a website ('.$lead->website.'), so pitch this preview as a clearer, stronger upgrade.',
            'Do not say they have no website.',
        ];

        foreach ($issues as $issue) {
            $points[] = 'Audit note: '.$issue;
        }

        if ($issues === []) {
            $points[] = 'Audit note: none recorded. Keep the comparison general and do not invent problems.';
        }

        return $points;
    }

    /**
     * Last-line defense against the model calling the paid website free.
     */
    protected function stripFreeClaims(string $script): string
    {
        $replacements = [
            '/\bcompletely free\b/iu' => 'ready for you to review',
            '/\btotally free\b/iu' => 'ready for you to review',
            '/\ba free website\b/iu' => 'a website preview',
            '/\bfree website\b/iu' => 'website preview',
            '/\bfor free\b/iu' => 'to review',
            '/\bat no cost\b/iu' => 'to review',
            '/\bno[- ]cost\b/iu' => 'preview',
            '/\bcomplimentary\b/iu' => 'custom',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $script = (string) preg_replace($pattern, $replacement, $script);
        }

        return trim(preg_replace('/\s{2,}/', ' ', $script) ?? $script);
    }
}

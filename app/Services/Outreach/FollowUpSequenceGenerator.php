<?php

namespace App\Services\Outreach;

use App\Ai\Agents\FollowUpSequenceDesigner;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\AiAvailability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FollowUpSequenceGenerator
{
    private const STEP_COUNT = 4;

    /** @var list<int> */
    private const DEFAULT_DELAYS = [1, 2, 3, 4];

    public static function fromConfig(): self
    {
        return new self;
    }

    public function available(): bool
    {
        return AiAvailability::anthropicConfigured();
    }

    /**
     * @return array{name: string, steps: list<array{delay_days: int, subject: string, body: string}>}
     */
    public function generate(Team $team, User $sender, string $prompt): array
    {
        if (! $this->available()) {
            throw ValidationException::withMessages([
                'prompt' => __('Add ANTHROPIC_API_KEY in your environment to draft sequences with AI.'),
            ]);
        }

        try {
            $response = (new FollowUpSequenceDesigner($this->systemPrompt()))
                ->prompt($this->userPrompt($team, $sender, $prompt));

            $raw = [
                'name' => $response['name'] ?? '',
                'steps' => $response['steps'] ?? [],
            ];
        } catch (Throwable $exception) {
            Log::warning('Follow-up sequence AI generation failed.', [
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'prompt' => __('The AI service is unavailable right now. Try again in a moment.'),
            ]);
        }

        return $this->normalize($raw);
    }

    protected function systemPrompt(): string
    {
        $tags = implode(', ', array_map(
            fn (string $key) => '{{'.$key.'}}',
            array_keys(MergeFieldRenderer::FIELDS),
        ));

        return <<<PROMPT
You write follow-up email sequences for a web agency that sells websites to local businesses.

Return JSON only with:
- name: short sequence title (max 80 characters)
- steps: exactly 4 objects, each with delay_days (integer 0-60), subject (max 150 chars), body (max 5000 chars)

Timing rules:
- Step 1 delay_days = days after a lead is enrolled before the first email sends
- Steps 2-4 delay_days = days to wait after the previous email before sending the next
- Default delays should be 1, 2, 3, 4 unless the user asks for different spacing

Use merge tags where natural: {$tags}
Tone: helpful, professional, concise. No hype, fake urgency, or invented guarantees.
Sign emails with {{sender_name}} and {{team_name}} on their own lines when appropriate.
PROMPT;
    }

    protected function userPrompt(Team $team, User $sender, string $prompt): string
    {
        return implode("\n", [
            'Workspace: '.$team->name,
            'Sender name: '.$sender->name,
            'Goal: '.$prompt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{name: string, steps: list<array{delay_days: int, subject: string, body: string}>}
     */
    protected function normalize(array $raw): array
    {
        $name = trim((string) ($raw['name'] ?? ''));

        if ($name === '') {
            $name = 'Follow-up sequence';
        }

        $name = Str::limit($name, 80, '');

        $steps = $raw['steps'] ?? [];

        if (! is_array($steps) || count($steps) < self::STEP_COUNT) {
            throw ValidationException::withMessages([
                'prompt' => __('AI returned an incomplete sequence. Try again with a bit more detail about your goal.'),
            ]);
        }

        $normalized = [];

        for ($index = 0; $index < self::STEP_COUNT; $index++) {
            $step = is_array($steps[$index] ?? null) ? $steps[$index] : [];
            $delay = (int) ($step['delay_days'] ?? self::DEFAULT_DELAYS[$index]);
            $delay = max(0, min(60, $delay));
            $subject = trim((string) ($step['subject'] ?? ''));
            $body = trim((string) ($step['body'] ?? ''));

            if ($subject === '' || $body === '') {
                throw ValidationException::withMessages([
                    'prompt' => __('AI returned an incomplete sequence. Try again with a bit more detail about your goal.'),
                ]);
            }

            $normalized[] = [
                'delay_days' => $delay,
                'subject' => Str::limit($subject, 150, ''),
                'body' => Str::limit($body, 5000, ''),
            ];
        }

        return [
            'name' => $name,
            'steps' => $normalized,
        ];
    }
}

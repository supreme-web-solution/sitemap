<?php

namespace App\Services\Assistant;

use App\Ai\Agents\SiteVisitorAssistant;
use App\Models\SiteAssistant;
use App\Services\Ai\AiAvailability;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Produces the assistant's answer to one visitor message.
 */
class AssistantResponder
{
    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{reply: string, ask_for_contact: bool}
     */
    public function reply(SiteAssistant $assistant, string $message, array $history = []): array
    {
        if (! AiAvailability::anthropicConfigured() || $this->overDailyCap($assistant)) {
            return $this->fallback($assistant);
        }

        $assistant->loadMissing('site.lead');

        try {
            $response = (new SiteVisitorAssistant(
                $assistant->site->lead->name,
                $assistant->name,
                (string) $assistant->knowledge,
                $this->cleanHistory($history),
            ))->prompt(Str::limit(trim($message), (int) config('assistant.max_message_length'), ''));

            $reply = trim((string) ($response['reply'] ?? ''));
            $askForContact = (bool) ($response['ask_for_contact'] ?? false);
        } catch (Throwable $exception) {
            Log::warning('Website assistant reply failed.', [
                'site_id' => $assistant->site_id,
                'message' => $exception->getMessage(),
            ]);

            return $this->fallback($assistant);
        }

        if ($reply === '') {
            return $this->fallback($assistant);
        }

        $assistant->newQuery()->whereKey($assistant->id)->increment('messages_count');

        return ['reply' => $reply, 'ask_for_contact' => $askForContact];
    }

    /**
     * Keep only the recent, well-formed turns of the conversation the visitor's browser sent back.
     *
     * @param  list<array{role?: mixed, content?: mixed}>  $history
     * @return list<array{role: string, content: string}>
     */
    public function cleanHistory(array $history): array
    {
        $turns = [];

        foreach ($history as $turn) {
            $role = $turn['role'] ?? null;
            $content = is_string($turn['content'] ?? null) ? trim($turn['content']) : '';

            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $turns[] = ['role' => $role, 'content' => Str::limit($content, (int) config('assistant.max_message_length'), '')];
        }

        $turns = array_slice($turns, -((int) config('assistant.history_turns') * 2));

        while ($turns !== [] && $turns[0]['role'] !== 'user') {
            array_shift($turns);
        }

        while ($turns !== [] && $turns[array_key_last($turns)]['role'] === 'user') {
            array_pop($turns);
        }

        return $turns;
    }

    /**
     * What the visitor hears when the AI cannot answer: take their details so the team can follow up.
     *
     * @return array{reply: string, ask_for_contact: bool}
     */
    protected function fallback(SiteAssistant $assistant): array
    {
        return [
            'reply' => __("I can't look that up right now, but I'd love for the team to help you. Leave your name and a phone number or email and they'll get back to you."),
            'ask_for_contact' => true,
        ];
    }

    /**
     * Count the message against the website's daily allowance.
     */
    protected function overDailyCap(SiteAssistant $assistant): bool
    {
        $key = 'assistant-messages:'.$assistant->site_id.':'.now()->format('Ymd');

        Cache::add($key, 0, now()->addDay());

        return Cache::increment($key) > (int) config('assistant.daily_message_cap');
    }
}

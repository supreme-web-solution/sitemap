<?php

namespace App\Services\Proposals;

use App\Ai\Agents\ProposalCopyDesigner;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Ai\AiAvailability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProposalCopyGenerator
{
    public function available(): bool
    {
        return AiAvailability::anthropicConfigured();
    }

    /**
     * @param  list<array{description?: string, quantity?: int|float|string, unit_price?: int|float|string, interval?: string|null}>  $items
     * @return array{intro: string, terms: string}
     */
    public function generate(Team $team, Lead $lead, string $title, array $items = []): array
    {
        if (! $this->available()) {
            throw ValidationException::withMessages([
                'draft' => __('Add ANTHROPIC_API_KEY in your environment to draft proposal copy with AI.'),
            ]);
        }

        try {
            $response = (new ProposalCopyDesigner($this->systemPrompt()))
                ->prompt($this->userPrompt($team, $lead, $title, $items));

            $intro = trim((string) ($response['intro'] ?? ''));
            $terms = trim((string) ($response['terms'] ?? ''));
        } catch (Throwable $exception) {
            Log::warning('Proposal copy AI generation failed.', [
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'draft' => __('The AI service is unavailable right now. Try again in a moment.'),
            ]);
        }

        if ($intro === '' || $terms === '') {
            throw ValidationException::withMessages([
                'draft' => __('The AI did not return usable copy. Try again.'),
            ]);
        }

        return [
            'intro' => Str::limit($intro, 5000, ''),
            'terms' => Str::limit($terms, 5000, ''),
        ];
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You write proposal copy for a web agency that sells websites and hosting to local businesses.

Return JSON only with:
- intro: warm, professional opening the client reads first (2-4 short paragraphs, plain text, max 1200 characters). Speak to the business owner. Mention the value of a clear website without inventing stats, awards, or guarantees.
- terms: practical plain-text terms (bullet-like lines or short paragraphs, max 1500 characters). Cover payment on acceptance, scope changes, and a reasonable delivery window. Do not invent legal clauses the agency did not ask for.

Tone: confident, clear, human. No markdown, no HTML, no emojis.
PROMPT;
    }

    /**
     * @param  list<array{description?: string, quantity?: int|float|string, unit_price?: int|float|string, interval?: string|null}>  $items
     */
    protected function userPrompt(Team $team, Lead $lead, string $title, array $items): string
    {
        $brand = $team->brand()['name'];
        $lines = [
            "Agency: {$brand}",
            "Client: {$lead->name}",
            'Category: '.($lead->category ?: 'local business'),
            'City: '.($lead->city ?: 'unknown'),
            "Proposal title: {$title}",
            'Line items:',
        ];

        foreach ($items as $item) {
            $description = trim((string) ($item['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = number_format((float) ($item['unit_price'] ?? 0), 2);
            $interval = filled($item['interval'] ?? null) ? ' / '.$item['interval'] : ' one-time';
            $lines[] = "- {$description} × {$quantity} @ \${$price}{$interval}";
        }

        if (count($lines) === 6) {
            $lines[] = '- (items not filled in yet)';
        }

        $lines[] = 'Write intro and terms for this proposal.';

        return implode("\n", $lines);
    }
}

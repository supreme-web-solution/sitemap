<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Facades\Http;

class ClaudeSitePlanner extends AiSitePlanner
{
    protected const BASE_URL = 'https://api.anthropic.com/v1';

    protected const TOOL_NAME = 'submit_site_plan';

    /**
     * Ask Claude for a site plan through a forced tool call.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function requestPlan(string $systemPrompt, string $userPrompt, array $schema): array
    {
        $response = Http::baseUrl(self::BASE_URL)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->acceptJson()
            ->timeout(60)
            ->post('messages', [
                'model' => $this->model,
                'max_tokens' => 2048,
                'system' => $systemPrompt,
                'tools' => [[
                    'name' => self::TOOL_NAME,
                    'description' => 'Submit the website plan for this business.',
                    'input_schema' => $schema,
                ]],
                'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
            ])
            ->throw();

        foreach ($response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === self::TOOL_NAME) {
                return is_array($block['input'] ?? null) ? $block['input'] : [];
            }
        }

        return [];
    }

    /**
     * The provider name used in logs.
     */
    protected function providerName(): string
    {
        return 'Claude';
    }
}

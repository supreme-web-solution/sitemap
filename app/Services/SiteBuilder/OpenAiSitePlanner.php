<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Facades\Http;

class OpenAiSitePlanner extends AiSitePlanner
{
    protected const BASE_URL = 'https://api.openai.com/v1';

    /**
     * Keywords OpenAI strict structured outputs reject, with the hint that replaces them.
     *
     * @var array<string, string>
     */
    protected const UNSUPPORTED_KEYWORDS = [
        'maxLength' => 'At most %s characters.',
        'minItems' => 'At least %s items.',
        'maxItems' => 'At most %s items.',
        'uniqueItems' => 'No duplicates.',
    ];

    /**
     * Ask OpenAI for a site plan using strict structured outputs.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function requestPlan(string $systemPrompt, string $userPrompt, array $schema): array
    {
        $response = Http::baseUrl(self::BASE_URL)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout(60)
            ->post('chat/completions', [
                'model' => $this->model,
                'max_completion_tokens' => 8000,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'site_plan',
                        'strict' => true,
                        'schema' => $this->strictSchema($schema),
                    ],
                ],
            ])
            ->throw();

        $content = $response->json('choices.0.message.content');
        $plan = is_string($content) ? json_decode($content, true) : null;

        return is_array($plan) ? $plan : [];
    }

    /**
     * Convert the plan schema into the subset strict mode accepts.
     *
     * Limits strict mode cannot enforce become description hints; they are
     * still enforced afterwards by the shared validation.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function strictSchema(array $schema): array
    {
        $hints = [];

        foreach (self::UNSUPPORTED_KEYWORDS as $keyword => $hint) {
            if (array_key_exists($keyword, $schema)) {
                $hints[] = sprintf($hint, var_export($schema[$keyword], true));
                unset($schema[$keyword]);
            }
        }

        if ($hints !== []) {
            $schema['description'] = trim(($schema['description'] ?? '').' '.implode(' ', $hints));
        }

        if (($schema['type'] ?? null) === 'object') {
            $schema['properties'] = array_map(fn (array $property) => $this->strictSchema($property), $schema['properties'] ?? []);
            $schema['required'] = array_keys($schema['properties']);
            $schema['additionalProperties'] = false;
        }

        if (isset($schema['items'])) {
            $schema['items'] = $this->strictSchema($schema['items']);
        }

        return $schema;
    }

    /**
     * The provider name used in logs.
     */
    protected function providerName(): string
    {
        return 'OpenAI';
    }
}

<?php

namespace App\Services\SiteBuilder;

use App\Data\SiteDraft;
use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use App\Models\Lead;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Plans a site with an AI model that may only fill in a strict schema.
 *
 * Providers implement the API call; validation, copy guardrails, retries and
 * the rule-based fallback are shared so every provider follows the same rules.
 */
abstract class AiSitePlanner implements SiteCopywriter
{
    public const MAX_ATTEMPTS = 2;

    /**
     * Claims the planner may only make when the business profile supports them.
     *
     * @var array<string, string>
     */
    protected const UNSUPPORTED_CLAIMS = [
        '/\$\s?\d/' => 'Do not mention prices.',
        '/\b(since|established|est\.?|founded)\s+(in\s+)?\d{4}\b/i' => 'Do not mention founding years.',
        '/\b\d+\+?\s+years?\b/i' => 'Do not mention years of experience.',
        '/\baward/i' => 'Do not mention awards.',
        '/\b(licen[cs]ed|certified|accredited|insured)\b/i' => 'Do not mention licences, certifications or insurance.',
        '/\b(guarantee|warranty)/i' => 'Do not mention guarantees or warranties.',
        '/\b(#1|number one|best in)\b/i' => 'Do not claim to be the best or number one.',
    ];

    public function __construct(
        protected string $apiKey,
        protected string $model,
        protected RuleBasedCopywriter $fallback,
    ) {}

    /**
     * Send the prompts to the provider and return the plan it produced.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    abstract protected function requestPlan(string $systemPrompt, string $userPrompt, array $schema): array;

    /**
     * The provider name used in logs.
     */
    abstract protected function providerName(): string;

    /**
     * Plan the site with the AI model, falling back to rule-based copy on failure.
     */
    public function write(Lead $lead, ?SiteTemplate $template = null): SiteDraft
    {
        $template ??= $lead->vibe?->template();
        $errors = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $plan = $this->requestPlan(
                    $this->systemPrompt(),
                    $this->userPrompt($lead, $template, $errors),
                    $this->planSchema($template),
                );
            } catch (ConnectionException|RequestException $exception) {
                Log::warning("{$this->providerName()} site planner request failed.", ['lead_id' => $lead->id, 'message' => $exception->getMessage()]);

                break;
            }

            $errors = $this->validationErrors($plan, $lead, $template);

            if ($errors === []) {
                return $this->toDraft($plan);
            }
        }

        Log::info("{$this->providerName()} site planner fell back to rule-based copy.", ['lead_id' => $lead->id, 'errors' => $errors]);

        return $this->fallback->write($lead, $template);
    }

    /**
     * Revise an existing plan from a plain-English instruction, falling back to the rule-based editor.
     */
    public function revise(Lead $lead, SiteDraft $current, string $instruction): SiteDraft
    {
        $errors = [];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $plan = $this->requestPlan(
                    $this->systemPrompt(),
                    $this->revisePrompt($lead, $current, $instruction, $errors),
                    $this->planSchema($current->template),
                );
            } catch (ConnectionException|RequestException $exception) {
                Log::warning("{$this->providerName()} site editor request failed.", ['lead_id' => $lead->id, 'message' => $exception->getMessage()]);

                break;
            }

            $errors = $this->validationErrors($plan, $lead, $current->template);

            if ($errors === []) {
                return $this->toDraft($plan);
            }
        }

        Log::info("{$this->providerName()} site editor fell back to rule-based edits.", ['lead_id' => $lead->id, 'errors' => $errors]);

        return $this->fallback->revise($lead, $current, $instruction);
    }

    /**
     * Validate the plan against the schema and the copy guardrails.
     *
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    protected function validationErrors(array $plan, Lead $lead, ?SiteTemplate $template): array
    {
        if ($plan === []) {
            return ['Return the site plan as JSON matching the schema.'];
        }

        $validator = Validator::make($plan, SiteContentSchema::rules());

        if ($validator->fails()) {
            return array_values($validator->errors()->all());
        }

        $errors = [];
        $chosenTemplate = SiteTemplate::from($plan['template']);

        if ($template !== null && $chosenTemplate !== $template) {
            $errors[] = "The template must be {$template->value}.";
        }

        if (! in_array(strtolower($plan['accent']), $chosenTemplate->palette(), true)) {
            $errors[] = 'The accent must be one of: '.implode(', ', $chosenTemplate->palette()).'.';
        }

        $copy = implode("\n", [
            $plan['headline'],
            $plan['tagline'],
            $plan['about'],
            ...array_map(fn (array $service) => $service['title'].' '.$service['description'], $plan['services']),
            ...array_map(fn (array $faq) => $faq['question'].' '.$faq['answer'], $plan['faqs']),
        ]);
        $source = (string) $lead->description;

        foreach (self::UNSUPPORTED_CLAIMS as $pattern => $message) {
            if (preg_match($pattern, $copy) && ! preg_match($pattern, $source)) {
                $errors[] = $message;
            }
        }

        if ($lead->rating === null && preg_match('/\b(star|rated|rating|reviews?)\b/i', $copy)) {
            $errors[] = 'Do not mention ratings or reviews; this business has none.';
        }

        return $errors;
    }

    /**
     * Convert a valid plan into a site draft.
     *
     * @param  array<string, mixed>  $plan
     */
    protected function toDraft(array $plan): SiteDraft
    {
        return new SiteDraft(
            template: SiteTemplate::from($plan['template']),
            content: SiteContentSchema::toContent($plan, withDefaults: true),
        );
    }

    /**
     * The system prompt that constrains the model to the section library.
     */
    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
        You plan one-page websites for small local businesses. You never design: you choose a template, an accent colour from that template's approved palette, and an order of sections from a fixed library, then write the copy.

        Copy rules:
        - Use only facts present in the business profile. Never invent prices, founding years, years of experience, awards, licences, certifications, guarantees, team members, testimonials or statistics.
        - Mention the rating or review count only when the profile includes them.
        - Services must be things this kind of business plainly offers; describe them in general terms.
        - FAQs may only answer from the profile (location, hours, phone, email). Return an empty list if there is nothing factual to answer.
        - Plain, warm, confident English. No exclamation marks, no emojis, no ALL CAPS.
        - The headline is at most 70 characters and should name what the business does and where.

        Section rules:
        - Include "gallery" only when the profile says photos are available.
        - Include "hours" only when the profile has opening hours or an address.
        - Always include "services" and "contact".
        PROMPT;
    }

    /**
     * Build the user message with the business profile.
     *
     * @param  list<string>  $previousErrors
     */
    protected function userPrompt(Lead $lead, ?SiteTemplate $template, array $previousErrors): string
    {
        $profile = array_filter([
            'name' => $lead->name,
            'category' => $lead->category,
            'description' => $lead->description,
            'address' => $lead->address,
            'city' => $lead->city,
            'country' => $lead->country,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'rating' => $lead->rating,
            'reviews_count' => $lead->reviews_count ?: null,
            'opening_hours' => $lead->opening_hours ?: null,
            'photos_available' => count($lead->photoUrls()),
            'preferred_style' => $lead->vibe?->label(),
        ], fn ($value) => $value !== null && $value !== '');

        $message = "Business profile:\n".json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($template !== null) {
            $message .= "\n\nUse the {$template->value} template.";
        }

        if ($previousErrors !== []) {
            $message .= "\n\nYour previous plan was rejected. Fix these problems:\n- ".implode("\n- ", $previousErrors);
        }

        return $message;
    }

    /**
     * Ask the model to return a full updated plan, not a patch.
     *
     * @param  list<string>  $previousErrors
     */
    protected function revisePrompt(Lead $lead, SiteDraft $current, string $instruction, array $previousErrors): string
    {
        $message = $this->userPrompt($lead, $current->template, $previousErrors);
        $message .= "\n\nCurrent website plan:\n".json_encode([
            'template' => $current->template->value,
            ...$current->content,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $message .= "\n\nApply this edit and return the complete updated plan. Keep every fact that the instruction does not change:\n".$instruction;

        return $message;
    }

    /**
     * The JSON schema the model must fill in.
     *
     * @return array<string, mixed>
     */
    protected function planSchema(?SiteTemplate $template): array
    {
        $templates = $template ? [$template] : SiteTemplate::cases();

        return [
            'type' => 'object',
            'properties' => [
                'template' => [
                    'type' => 'string',
                    'enum' => array_map(fn (SiteTemplate $option) => $option->value, $templates),
                ],
                'accent' => [
                    'type' => 'string',
                    'description' => 'Hex colour from the chosen template palette: '.implode('; ', array_map(
                        fn (SiteTemplate $option) => $option->value.' = '.implode(', ', $option->palette()),
                        $templates,
                    )),
                ],
                'headline' => ['type' => 'string', 'maxLength' => SiteContentSchema::HEADLINE_MAX],
                'tagline' => ['type' => 'string', 'maxLength' => 200],
                'cta_label' => ['type' => 'string', 'maxLength' => 40],
                'about' => ['type' => 'string', 'maxLength' => 2000],
                'sections' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => SiteSection::defaultOrder()],
                    'uniqueItems' => true,
                ],
                'services' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 6,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'maxLength' => 60],
                            'description' => ['type' => 'string', 'maxLength' => 250],
                        ],
                        'required' => ['title', 'description'],
                    ],
                ],
                'faqs' => [
                    'type' => 'array',
                    'maxItems' => 5,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string', 'maxLength' => 150],
                            'answer' => ['type' => 'string', 'maxLength' => 600],
                        ],
                        'required' => ['question', 'answer'],
                    ],
                ],
            ],
            'required' => ['template', 'accent', 'headline', 'tagline', 'cta_label', 'about', 'sections', 'services', 'faqs'],
        ];
    }
}

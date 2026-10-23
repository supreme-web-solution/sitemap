<?php

use App\Enums\SiteTemplate;
use App\Models\Lead;
use App\Services\SiteBuilder\ClaudeSitePlanner;
use App\Services\SiteBuilder\OpenAiSitePlanner;
use App\Services\SiteBuilder\RuleBasedCopywriter;
use App\Services\SiteBuilder\SiteCopywriter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.site_planner.provider' => null,
        'services.anthropic.key' => null,
        'services.openai.key' => 'sk-test',
        'services.openai.model' => 'gpt-test',
    ]);
});

/**
 * Build a fake OpenAI Chat Completions response carrying a site plan.
 *
 * @param  array<string, mixed>|string  $plan
 * @return array<string, mixed>
 */
function openAiPlanResponse(array|string $plan = []): array
{
    $content = is_string($plan) ? $plan : json_encode([
        'template' => 'modern-clean',
        'accent' => '#0d9488',
        'headline' => 'Dental Care in Austin',
        'tagline' => 'Gentle checkups and cleanings close to home.',
        'cta_label' => 'Book a Visit',
        'about' => 'Bright Smile Dental looks after families across Austin.',
        'sections' => ['services', 'about', 'contact'],
        'services' => [
            ['title' => 'Checkups', 'description' => 'Routine exams for the whole family.'],
            ['title' => 'Cleanings', 'description' => 'Professional cleaning for a healthy smile.'],
            ['title' => 'Whitening', 'description' => 'Brighten your smile.'],
        ],
        'faqs' => [],
        ...$plan,
    ]);

    return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
}

test('a valid plan from OpenAI becomes the site draft', function () {
    Http::fake(['api.openai.com/*' => Http::response(openAiPlanResponse())]);

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create(['name' => 'Bright Smile Dental']));

    expect($draft->template)->toBe(SiteTemplate::ModernClean);
    expect($draft->content['headline'])->toBe('Dental Care in Austin');

    Http::assertSent(function (Request $request) {
        $schema = $request['response_format']['json_schema']['schema'];

        return $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request['model'] === 'gpt-test'
            && $request['response_format']['json_schema']['strict'] === true
            && $schema['additionalProperties'] === false
            && $schema['properties']['services']['items']['additionalProperties'] === false
            && ! array_key_exists('maxLength', $schema['properties']['headline'])
            && str_contains($schema['properties']['headline']['description'], 'At most 70 characters.')
            && str_contains($request['messages'][1]['content'], 'Bright Smile Dental');
    });
});

test('an OpenAI plan that breaks the rules is retried with the errors', function () {
    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(openAiPlanResponse(['about' => 'Serving Austin since 1998.']))
        ->push(openAiPlanResponse()),
    ]);

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create(['description' => null]));

    expect($draft->content['about'])->toBe('Bright Smile Dental looks after families across Austin.');
    Http::assertSent(fn (Request $request) => str_contains($request['messages'][1]['content'], 'Do not mention founding years.'));
});

test('an unreadable OpenAI reply is retried', function () {
    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(openAiPlanResponse('not json'))
        ->push(openAiPlanResponse()),
    ]);

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create());

    expect($draft->content['headline'])->toBe('Dental Care in Austin');
    Http::assertSentCount(2);
});

test('rule based copy is used when the OpenAI API is unavailable', function () {
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limited']], 429)]);

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create(['category' => 'Plumber', 'city' => 'Austin']));

    expect($draft->content['headline'])->toBe('Trusted Plumbing in Austin');
    Http::assertSentCount(1);
});

test('the planner provider follows the configured keys', function (?string $preferred, ?string $anthropicKey, ?string $openAiKey, string $expected) {
    config([
        'services.site_planner.provider' => $preferred,
        'services.anthropic.key' => $anthropicKey,
        'services.openai.key' => $openAiKey,
    ]);

    expect(app(SiteCopywriter::class))->toBeInstanceOf($expected);
})->with([
    'only openai' => [null, null, 'sk-test', OpenAiSitePlanner::class],
    'only anthropic' => [null, 'ak-test', null, ClaudeSitePlanner::class],
    'both, openai preferred' => ['openai', 'ak-test', 'sk-test', OpenAiSitePlanner::class],
    'both, no preference' => [null, 'ak-test', 'sk-test', ClaudeSitePlanner::class],
    'preferred provider has no key' => ['openai', 'ak-test', null, ClaudeSitePlanner::class],
    'no keys' => [null, null, null, RuleBasedCopywriter::class],
]);

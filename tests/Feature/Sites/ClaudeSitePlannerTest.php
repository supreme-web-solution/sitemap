<?php

use App\Ai\Agents\SitePlanDesigner;
use App\Enums\SiteTemplate;
use App\Enums\SiteVibe;
use App\Models\Lead;
use App\Services\SiteBuilder\ClaudeSitePlanner;
use App\Services\SiteBuilder\SiteCopywriter;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    config([
        'services.anthropic.key' => 'test-key',
        'services.anthropic.model' => 'claude-test',
        'services.openai.key' => null,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sitePlanInput(array $overrides = []): array
{
    return [
        'template' => 'modern-clean',
        'accent' => '#0d9488',
        'headline' => 'Dental Care in Austin',
        'tagline' => 'Gentle checkups and cleanings close to home.',
        'cta_label' => 'Book a Visit',
        'about' => 'Bright Smile Dental looks after families across Austin.',
        'sections' => ['services', 'about', 'contact'],
        'services' => [
            [
                'title' => 'Checkups',
                'description' => 'Routine exams for the whole family, explained in plain language before anything starts and finished with care.',
                'body' => "Routine exams for the whole family, explained in plain language before anything starts and finished with care.\n\nYou say what you need, we look at the visit, and we agree the next step before anything begins.",
            ],
            [
                'title' => 'Cleanings',
                'description' => 'Professional cleaning for a healthy smile, with a clear plan for what will be done during the visit.',
                'body' => "Professional cleaning for a healthy smile, with a clear plan for what will be done during the visit.\n\nYou say what you need, we look at the visit, and we agree the next step before anything begins.",
            ],
            [
                'title' => 'Whitening',
                'description' => 'Brighten your smile with a careful treatment talked through before it starts.',
                'body' => "Brighten your smile with a careful treatment talked through before it starts.\n\nYou say what you need, we look at the visit, and we agree the next step before anything begins.",
            ],
            [
                'title' => 'Urgent Visits',
                'description' => 'Get seen when tooth pain cannot wait, with clear next steps before the work begins.',
                'body' => "Get seen when tooth pain cannot wait, with clear next steps before the work begins.\n\nYou say what you need, we look at the visit, and we agree the next step before anything begins.",
            ],
        ],
        'faqs' => [],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fakeSitePlanToolCall(array $overrides = []): ToolCall
{
    return new ToolCall(
        'tool_1',
        'SubmitSitePlan',
        ['plan_json' => json_encode(sitePlanInput($overrides), JSON_THROW_ON_ERROR)],
    );
}

test('string variants from the model are normalized before validation', function () {
    SitePlanDesigner::fake(fn () => fakeSitePlanToolCall([
        'variants' => json_encode(['footer' => 'columns'], JSON_THROW_ON_ERROR),
    ]));

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create());

    expect($draft->content['variants']['footer'] ?? null)->toBe('columns');
});

test('a valid plan from Claude becomes the site draft', function () {
    SitePlanDesigner::fake(fn () => fakeSitePlanToolCall());

    $lead = Lead::factory()->create(['name' => 'Bright Smile Dental', 'category' => 'Dentist', 'city' => 'Austin']);

    $draft = app(SiteCopywriter::class)->write($lead);

    expect($draft->template)->toBe(SiteTemplate::ModernClean);
    expect($draft->content)
        ->headline->toBe('Dental Care in Austin')
        ->accent->toBe('#0d9488')
        ->sections->toBe(['services', 'about', 'contact']);

    SitePlanDesigner::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Bright Smile Dental'));
});

test('an invalid plan is retried with the validation errors', function () {
    SitePlanDesigner::fake([
        fakeSitePlanToolCall(['headline' => str_repeat('Too long ', 20)]),
        fakeSitePlanToolCall(),
    ]);

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create());

    expect($draft->content['headline'])->toBe('Dental Care in Austin');
});

test('invented facts are rejected', function (array $overrides, string $error) {
    SitePlanDesigner::fake([
        fakeSitePlanToolCall($overrides),
        fakeSitePlanToolCall(),
    ]);

    app(SiteCopywriter::class)->write(Lead::factory()->create(['description' => null, 'rating' => 4.8]));
})->with([
    'founding year' => [['about' => 'Serving Austin since 1998.'], 'Do not mention founding years.'],
    'prices' => [['tagline' => 'Cleanings from $49.'], 'Do not mention prices.'],
    'awards' => [['about' => 'Our award-winning team.'], 'Do not mention awards.'],
]);

test('ratings are only mentioned when the business has them', function () {
    SitePlanDesigner::fake([
        fakeSitePlanToolCall(['tagline' => 'Five star rated dentists.']),
        fakeSitePlanToolCall(),
    ]);

    app(SiteCopywriter::class)->write(Lead::factory()->create(['rating' => null]));
});

test('the accent must come from the template palette', function () {
    SitePlanDesigner::fake([
        fakeSitePlanToolCall(['accent' => '#ff00ff']),
        fakeSitePlanToolCall(),
    ]);

    app(SiteCopywriter::class)->write(Lead::factory()->create());
});

test('the owner vibe locks the template', function () {
    SitePlanDesigner::fake(fn () => fakeSitePlanToolCall(['template' => 'warm-studio', 'accent' => '#be123c']));

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create(['vibe' => SiteVibe::Warm]));

    expect($draft->template)->toBe(SiteTemplate::WarmStudio);

    SitePlanDesigner::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Use the warm-studio template.'));
});

test('rule based copy is used when Claude keeps failing validation', function () {
    SitePlanDesigner::fake(fn () => fakeSitePlanToolCall(['sections' => ['testimonials']]));

    $lead = Lead::factory()->create(['category' => 'Plumber', 'city' => 'Austin']);

    $draft = app(SiteCopywriter::class)->write($lead);

    expect($draft->content['headline'])->toBe('Trusted Plumbing in Austin');

    SitePlanDesigner::assertPromptedTimes(ClaudeSitePlanner::MAX_ATTEMPTS);
});

test('rule based copy is used when the Claude API is unavailable', function () {
    SitePlanDesigner::fake(fn () => throw new RuntimeException('Overloaded'));

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create(['category' => 'Plumber', 'city' => 'Austin']));

    expect($draft->content['headline'])->toBe('Trusted Plumbing in Austin');
});

<?php

use App\Enums\SiteTemplate;
use App\Enums\SiteVibe;
use App\Models\Lead;
use App\Services\SiteBuilder\ClaudeSitePlanner;
use App\Services\SiteBuilder\SiteCopywriter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.site_planner.provider' => null,
        'services.anthropic.key' => 'test-key',
        'services.anthropic.model' => 'claude-test',
        'services.openai.key' => null,
    ]);
});

/**
 * Build a fake Anthropic Messages API response carrying a site plan.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function claudePlanResponse(array $overrides = []): array
{
    return [
        'content' => [[
            'type' => 'tool_use',
            'name' => 'submit_site_plan',
            'input' => [
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
                ...$overrides,
            ],
        ]],
    ];
}

test('a valid plan from Claude becomes the site draft', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(claudePlanResponse())]);

    $lead = Lead::factory()->create(['name' => 'Bright Smile Dental', 'category' => 'Dentist', 'city' => 'Austin']);

    $draft = app(SiteCopywriter::class)->write($lead);

    expect($draft->template)->toBe(SiteTemplate::ModernClean);
    expect($draft->content)
        ->headline->toBe('Dental Care in Austin')
        ->accent->toBe('#0d9488')
        ->sections->toBe(['services', 'about', 'contact']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'test-key')
        && $request['model'] === 'claude-test'
        && $request['tool_choice'] === ['type' => 'tool', 'name' => 'submit_site_plan']
        && str_contains($request['messages'][0]['content'], 'Bright Smile Dental'));
});

test('an invalid plan is retried with the validation errors', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudePlanResponse(['headline' => str_repeat('Too long ', 20)]))
        ->push(claudePlanResponse()),
    ]);

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create());

    expect($draft->content['headline'])->toBe('Dental Care in Austin');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], 'The headline field must not be greater than 70 characters.'));
});

test('invented facts are rejected', function (array $overrides, string $error) {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudePlanResponse($overrides))
        ->push(claudePlanResponse()),
    ]);

    app(SiteCopywriter::class)->write(Lead::factory()->create(['description' => null, 'rating' => 4.8]));

    Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], $error));
})->with([
    'founding year' => [['about' => 'Serving Austin since 1998.'], 'Do not mention founding years.'],
    'prices' => [['tagline' => 'Cleanings from $49.'], 'Do not mention prices.'],
    'awards' => [['about' => 'Our award-winning team.'], 'Do not mention awards.'],
]);

test('ratings are only mentioned when the business has them', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudePlanResponse(['tagline' => 'Five star rated dentists.']))
        ->push(claudePlanResponse()),
    ]);

    app(SiteCopywriter::class)->write(Lead::factory()->create(['rating' => null]));

    Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], 'Do not mention ratings or reviews'));
});

test('the accent must come from the template palette', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudePlanResponse(['accent' => '#ff00ff']))
        ->push(claudePlanResponse()),
    ]);

    app(SiteCopywriter::class)->write(Lead::factory()->create());

    Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], 'The accent must be one of'));
});

test('the owner vibe locks the template', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(claudePlanResponse(['template' => 'warm-studio', 'accent' => '#be123c']))]);

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create(['vibe' => SiteVibe::Warm]));

    expect($draft->template)->toBe(SiteTemplate::WarmStudio);
    Http::assertSent(fn (Request $request) => $request['tools'][0]['input_schema']['properties']['template']['enum'] === ['warm-studio']);
});

test('rule based copy is used when Claude keeps failing validation', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(claudePlanResponse(['sections' => ['testimonials']]))]);

    $lead = Lead::factory()->create(['category' => 'Plumber', 'city' => 'Austin']);

    $draft = app(SiteCopywriter::class)->write($lead);

    expect($draft->content['headline'])->toBe('Trusted Plumbing in Austin');
    Http::assertSentCount(ClaudeSitePlanner::MAX_ATTEMPTS);
});

test('rule based copy is used when the Claude API is unavailable', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'Overloaded']], 529)]);

    $draft = app(SiteCopywriter::class)->write(Lead::factory()->create(['category' => 'Plumber', 'city' => 'Austin']));

    expect($draft->content['headline'])->toBe('Trusted Plumbing in Austin');
    Http::assertSentCount(1);
});

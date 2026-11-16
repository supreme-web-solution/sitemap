<?php

use App\Data\SiteImageGenerationPlan;
use App\Models\AiCreditEvent;
use App\Models\Lead;
use App\Models\User;
use App\Services\SiteBuilder\OpenAiImageGenerator;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('an openai account with no credits shows on the admin credit page', function () {
    config([
        'services.openai.key' => 'test-key',
        'admin.emails' => ['boss@example.com'],
    ]);

    $lead = Lead::factory()->create(['name' => 'Halls Handyman']);
    $admin = User::factory()->create(['email' => 'boss@example.com']);
    $plan = new SiteImageGenerationPlan('A storefront', '16:9', 'home_hero', 'Hero photo');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'error' => [
                'message' => 'You have no credits remaining. Add credits to continue using the API at https://platform.openai.com/settings/organization/billing/.',
                'type' => 'insufficient_quota',
                'param' => null,
                'code' => 'credit_balance_exhausted',
            ],
        ], 429),
    ]);

    $generate = fn () => app(OpenAiImageGenerator::class)->generate($plan, (int) $lead->team_id, $lead->id);

    expect($generate)->toThrow(RuntimeException::class);
    expect($generate)->toThrow(RuntimeException::class);
    expect(AiCreditEvent::query()->count())->toBe(1);

    $event = AiCreditEvent::query()->sole();

    expect($event->finished_at)->not->toBeNull()
        ->and($event->lead_id)->toBe($lead->id)
        ->and($event->team_id)->toBe($lead->team_id);

    $this->actingAs($admin)
        ->get(route('admin.ai-credits'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AiCredits')
            ->where('events.data.0.provider', 'OpenAI')
            ->where('events.data.0.source', 'Photo generation')
            ->where('events.data.0.leadName', 'Halls Handyman')
            ->where('events.data.0.message', 'You have no credits remaining. Add credits to continue using the API at https://platform.openai.com/settings/organization/billing/.')
            ->where('events.data.0.outcome', 'Photo was not generated')
            ->where('events.data.0.finishedAt', $event->finished_at->toIso8601String()));
});

test('an openai server error is not listed as a credit failure', function () {
    config(['services.openai.key' => 'test-key']);

    $lead = Lead::factory()->create();
    $plan = new SiteImageGenerationPlan('A storefront', '16:9', 'home_hero', 'Hero photo');

    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'The server had an error.']], 500),
    ]);

    expect(fn () => app(OpenAiImageGenerator::class)->generate($plan, (int) $lead->team_id, $lead->id))
        ->toThrow(RuntimeException::class);

    expect(AiCreditEvent::query()->count())->toBe(0);
});

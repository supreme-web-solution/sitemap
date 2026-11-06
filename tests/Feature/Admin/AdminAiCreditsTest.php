<?php

use App\Models\AiCreditEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['admin.emails' => ['boss@example.com']]);
    $this->admin = User::factory()->create(['email' => 'boss@example.com']);
});

test('only administrators can open the ai credit log', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('admin.ai-credits'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.ai-credits'))->assertOk();
});

test('the ai credit log shows the provider, the error, and when the job finished', function () {
    $event = AiCreditEvent::factory()->create([
        'message' => 'AI provider [anthropic] has insufficient credits or quota.',
        'occurred_at' => '2026-11-06 07:25:25',
        'finished_at' => '2026-11-06 07:25:27',
    ]);
    $event->refresh();

    $this->actingAs($this->admin)
        ->get(route('admin.ai-credits'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AiCredits')
            ->where('events.data.0.provider', 'Anthropic')
            ->where('events.data.0.source', 'Site creation')
            ->where('events.data.0.message', $event->message)
            ->where('events.data.0.outcome', 'Finished with built-in copy')
            ->where('events.data.0.leadName', $event->lead->name)
            ->where('events.data.0.occurredAt', $event->occurred_at->toIso8601String())
            ->where('events.data.0.finishedAt', $event->finished_at->toIso8601String()));
});

<?php

use App\Enums\SiteGenerationStatus;
use App\Enums\SiteTemplate;
use App\Enums\SiteVibe;
use App\Jobs\ApplySiteEdit;
use App\Jobs\GenerateSiteConcept;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

test('requesting three designs queues one job per vibe on the ai queue', function () {
    Bus::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('leads.concepts.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead]))
        ->assertRedirect();

    expect($lead->fresh()->concepts_status)->toBe(SiteGenerationStatus::Queued);

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3
        && $batch->jobs->every(fn ($job) => $job instanceof GenerateSiteConcept));
});

test('three concepts are generated and choosing one becomes the website', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'name' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
    ]);

    $this->actingAs($user)
        ->post(route('leads.concepts.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead]))
        ->assertRedirect();

    expect($lead->concepts()->count())->toBe(3)
        ->and($lead->fresh()->concepts_status)->toBe(SiteGenerationStatus::Idle)
        ->and($lead->concepts()->pluck('template')->map->value->sort()->values()->all())
        ->toBe(collect(SiteTemplate::cases())->map->value->sort()->values()->all());

    $concept = $lead->concepts()->where('template', SiteTemplate::WarmStudio)->firstOrFail();

    $this->get(route('sites.concepts.preview', $concept))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Austin', false);

    $this->actingAs($user)
        ->post(route('leads.concepts.choose', ['current_team' => $user->currentTeam->slug, 'lead' => $lead, 'concept' => $concept]))
        ->assertRedirect(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $lead->fresh()->site]));

    expect($lead->fresh())
        ->vibe->toBe(SiteVibe::Warm)
        ->and($lead->site->template)->toBe(SiteTemplate::WarmStudio);
});

test('chat edits are dispatched to the ai queue', function () {
    Queue::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'Change the headline to "Local Roofing Experts"',
        ])
        ->assertRedirect();

    Queue::assertPushedOn('ai', ApplySiteEdit::class);
});

test('a chat edit updates the headline and snapshots a version', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'Change the headline to "Local Roofing Experts"',
        ])
        ->assertRedirect();

    expect($site->fresh()->content['headline'])->toBe('Local Roofing Experts')
        ->and($site->versions()->count())->toBe(1)
        ->and(SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_ASSISTANT)->value('status'))->toBe(SiteChatMessage::STATUS_DONE);
});

test('ai editing is blocked when the plan leaves it out', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->forceFill(['plan_id' => Plan::factory()->create(['features' => []])->id])->save();
    $lead = Lead::factory()->for($team)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $team->slug, 'site' => $site]), [
            'body' => 'Change the headline to "Nope"',
        ]);

    expect(SiteChatMessage::count())->toBe(0);
    Queue::assertNothingPushed();

    $this->actingAs($user)
        ->post(route('leads.concepts.store', ['current_team' => $team->slug, 'lead' => $lead]));

    expect($lead->fresh()->concepts_status)->toBe(SiteGenerationStatus::Idle);
});

test('a second chat edit is rejected while one is still running', function () {
    Queue::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);
    $args = ['current_team' => $user->currentTeam->slug, 'site' => $site];

    $this->actingAs($user)->post(route('sites.chat.store', $args), ['body' => 'Change the headline to "First"']);
    $this->actingAs($user)->post(route('sites.chat.store', $args), ['body' => 'Change the headline to "Second"'])
        ->assertSessionHasErrors('body');

    expect(SiteChatMessage::where('role', SiteChatMessage::ROLE_USER)->count())->toBe(1);
});

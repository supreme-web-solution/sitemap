<?php

use App\Enums\SiteGenerationStatus;
use App\Jobs\GenerateLeadSite;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteBuilder\SiteBuildProgress;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

test('a site build writes each step to the log', function () {
    $channel = Mockery::mock();
    $messages = [];

    $channel->shouldReceive('info')->andReturnUsing(function (string $message) use (&$messages) {
        $messages[] = $message;
    });

    Log::shouldReceive('channel')->with('site-edit')->andReturn($channel);

    $lead = Lead::factory()->create(['site_generation_status' => SiteGenerationStatus::Queued]);

    GenerateLeadSite::dispatchSync($lead);

    // dispatchSync runs the job immediately, so there is no "Waiting for a worker" queue step.
    expect($messages)->toBe([
        '[site-build] Build started',
        '[site-build] Writing the website copy',
        '[site-build] Saving pages and layout',
        '[site-build] Preparing logo and brand assets',
        '[site-build] Website build finished',
    ]);
});

test('the snapshot marks finished, active and pending steps', function () {
    $lead = Lead::factory()->create();

    SiteBuildProgress::record($lead->id, 'queued');
    SiteBuildProgress::record($lead->id, 'started');
    SiteBuildProgress::record($lead->id, 'writing');

    $snapshot = SiteBuildProgress::snapshot($lead->id);

    expect($snapshot)
        ->step->toBe('writing')
        ->percent->toBe(60)
        ->stalled->toBeFalse();
    expect(collect($snapshot['steps'])->pluck('state', 'key')->all())->toBe([
        'queued' => 'done',
        'started' => 'done',
        'writing' => 'active',
        'saving' => 'pending',
        'assets' => 'pending',
    ]);
});

test('a build with no new step for a while is reported as stalled', function () {
    $lead = Lead::factory()->create();

    SiteBuildProgress::record($lead->id, 'started');

    $this->travel(SiteBuildProgress::STALLED_AFTER_SECONDS + 5)->seconds();

    expect(SiteBuildProgress::snapshot($lead->id)['stalled'])->toBeTrue();
});

test('a queued lead exposes its build progress in the leads list', function () {
    Queue::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'site_generation_status' => SiteGenerationStatus::Queued,
    ]);

    SiteBuildProgress::record($lead->id, 'writing');

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leads.data.0.siteBuild.step', 'writing')
            ->where('leads.data.0.siteBuild.percent', 60));
});

test('leads that are not building have no build progress', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $user->currentTeam->slug]))
        ->assertInertia(fn ($page) => $page->where('leads.data.0.siteBuild', null));
});

test('the review page exposes build progress while the site is building', function () {
    Queue::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'site_generation_status' => SiteGenerationStatus::Queued,
    ]);

    $this->actingAs($user)
        ->get(route('leads.edit', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('lead.siteBuild.step', 'queued')
            ->has('lead.siteBuild.steps'));
});

test('a stuck unique lock can be force released so the lead can build again', function () {
    $lead = Lead::factory()->create();
    $job = new GenerateLeadSite($lead);

    Cache::lock(UniqueLock::getKey($job), 60)->get();

    GenerateLeadSite::releaseUniqueLock($lead->id);

    expect(Cache::lock(UniqueLock::getKey($job), 1)->get())->toBeTrue();
});

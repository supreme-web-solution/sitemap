<?php

use App\Ai\Agents\PitchVideoScriptWriter;
use App\Enums\LeadActivityType;
use App\Enums\PitchVideoStatus;
use App\Jobs\CreatePitchVideo;
use App\Jobs\RefreshPitchVideo;
use App\Mail\ProposalEmail;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\Site;
use App\Models\SitePitchVideo;
use App\Models\User;
use App\Services\Did\DidClient;
use App\Services\Outreach\MergeFieldRenderer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function pitchSite(array $leadAttributes = []): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create($leadAttributes);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    return [$user, $site];
}

function pitchRoute(string $name, User $user, Site $site): string
{
    return route($name, ['current_team' => $user->currentTeam->slug, 'site' => $site->id]);
}

function fakeDidLists(): void
{
    config(['services.did.api_key' => 'test-key']);

    Http::fake([
        'api.d-id.com/clips/presenters*' => Http::response(['presenters' => [
            ['presenter_id' => 'v2_public_amber@abc', 'name' => 'Amber', 'gender' => 'female', 'thumbnail_url' => 'https://cdn.example.com/amber.jpg'],
            ['presenter_id' => 'v2_public_adam@def', 'name' => 'Adam', 'gender' => 'male', 'thumbnail_url' => 'https://cdn.example.com/adam.jpg'],
            ['presenter_id' => 'v2_public_green@ghi', 'name' => 'Green', 'gender' => 'male', 'thumbnail_url' => 'https://cdn.example.com/green.jpg', 'is_greenscreen' => true],
        ]]),
        'api.d-id.com/voices*' => Http::response([
            ['id' => 'en-US-JennyNeural', 'name' => 'Jenny', 'gender' => 'Female', 'provider' => 'microsoft', 'languages' => [['locale' => 'en-US']], 'preview_url' => 'https://cdn.example.com/jenny.mp3'],
            ['id' => 'en-US-GuyNeural', 'name' => 'Guy', 'gender' => 'Male', 'provider' => 'microsoft', 'languages' => [['locale' => 'en-US']]],
            ['id' => 'fr-FR-DeniseNeural', 'name' => 'Denise', 'gender' => 'Female', 'provider' => 'microsoft', 'languages' => [['locale' => 'fr-FR']]],
        ]),
    ]);
}

test('the did client lists non greenscreen presenters and english voices', function () {
    fakeDidLists();

    $client = app(DidClient::class);

    expect(collect($client->presenters())->pluck('id')->all())->toBe(['v2_public_amber@abc', 'v2_public_adam@def'])
        ->and(collect($client->voices())->pluck('id')->all())->toBe(['en-US-GuyNeural', 'en-US-JennyNeural']);
});

test('a male presenter is matched with a male voice and a female presenter with a female voice', function () {
    fakeDidLists();

    $client = app(DidClient::class);
    $voices = $client->voices();

    expect($client->voiceForGender('male', $voices)['id'])->toBe('en-US-GuyNeural')
        ->and($client->voiceForGender('female', $voices)['id'])->toBe('en-US-JennyNeural');
});

test('the editor loads presenters and voices only when they are asked for', function () {
    fakeDidLists();
    [$user, $site] = pitchSite();

    $url = route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]);

    $this->actingAs($user)->get($url)
        ->assertInertia(fn ($page) => $page
            ->where('pitchVideo.configured', true)
            ->where('pitchVideo.video', null)
            ->missing('pitchVideoOptions')
            ->reloadOnly('pitchVideoOptions', fn ($reload) => $reload
                ->where('pitchVideoOptions.defaultVoices.male', 'en-US-GuyNeural')
                ->where('pitchVideoOptions.defaultVoices.female', 'en-US-JennyNeural')));
});

test('ai writes a script for a business that has no website on its listing', function () {
    config(['services.anthropic.key' => 'test-key']);
    PitchVideoScriptWriter::fake([['script' => 'Hi Mike, we built a free website for your handyman business. Open the link below to see it.']]);
    [$user, $site] = pitchSite(['website' => null]);

    $this->actingAs($user)
        ->post(pitchRoute('sites.pitch-video.script', $user, $site))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($site->pitchVideo->script)->toContain('website preview')
        ->and($site->pitchVideo->script)->not->toContain('free')
        ->and($site->pitchVideo->status)->toBe(PitchVideoStatus::Draft);

    PitchVideoScriptWriter::assertPrompted(fn ($prompt) => str_contains((string) $prompt->prompt, 'no website on their listing')
        && str_contains((string) $prompt->prompt, 'Never call the website free'));
});

test('the script prompt tells the writer about a current website and its audit issues', function () {
    config(['services.anthropic.key' => 'test-key']);
    PitchVideoScriptWriter::fake([['script' => 'Hello, your current site loads slowly, and the new one fixes that.']]);
    [$user, $site] = pitchSite([
        'website' => 'https://old-site.example.com',
        'website_audit' => ['checks' => [['status' => 'fail', 'detail' => 'The page takes 9 seconds to load']]],
    ]);

    $this->actingAs($user)->post(pitchRoute('sites.pitch-video.script', $user, $site))->assertSessionHasNoErrors();

    PitchVideoScriptWriter::assertPrompted(fn ($prompt) => str_contains((string) $prompt->prompt, 'already have a website')
        && str_contains((string) $prompt->prompt, 'Do not say they have no website')
        && str_contains((string) $prompt->prompt, 'The page takes 9 seconds to load')
        && str_contains((string) $prompt->prompt, 'Never call the website free'));
});

test('scripts that still say free are cleaned before saving', function () {
    config(['services.anthropic.key' => 'test-key']);
    PitchVideoScriptWriter::fake([['script' => 'We built a free website for you, completely free, at no cost.']]);
    [$user, $site] = pitchSite(['website' => null]);

    $this->actingAs($user)->post(pitchRoute('sites.pitch-video.script', $user, $site))->assertSessionHasNoErrors();

    expect($site->pitchVideo->fresh()->script)->not->toMatch('/\bfree\b/i')
        ->and($site->pitchVideo->fresh()->script)->not->toMatch('/\bat no cost\b/i');
});

test('writing a script without an ai key explains what is missing', function () {
    config(['services.anthropic.key' => null, 'ai.providers.anthropic.key' => null]);
    [$user, $site] = pitchSite();

    $this->actingAs($user)
        ->post(pitchRoute('sites.pitch-video.script', $user, $site))
        ->assertSessionHasErrors('script');
});

test('creating a video saves the presenter, voice and script and starts the job', function () {
    fakeDidLists();
    Queue::fake();
    [$user, $site] = pitchSite();

    $this->actingAs($user)
        ->post(pitchRoute('sites.pitch-video.store', $user, $site), [
            'presenter_id' => 'v2_public_adam@def',
            'voice_id' => 'en-US-GuyNeural',
            'script' => 'Hi there, we built a brand new website for your business and it is ready to look at.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $video = $site->fresh()->pitchVideo;

    expect($video->status)->toBe(PitchVideoStatus::Generating)
        ->and($video->presenter_name)->toBe('Adam')
        ->and($video->presenter_gender)->toBe('male')
        ->and($video->voice_provider)->toBe('microsoft');

    Queue::assertPushed(CreatePitchVideo::class);
});

test('a presenter that D-ID does not offer is rejected', function () {
    fakeDidLists();
    Queue::fake();
    [$user, $site] = pitchSite();

    $this->actingAs($user)
        ->post(pitchRoute('sites.pitch-video.store', $user, $site), [
            'presenter_id' => 'made-up',
            'voice_id' => 'en-US-GuyNeural',
            'script' => 'Hi there, we built a brand new website for your business and it is ready to look at.',
        ])
        ->assertSessionHasErrors('presenter_id');

    Queue::assertNothingPushed();
});

test('a video cannot be created while one is already being made', function () {
    fakeDidLists();
    Queue::fake();
    [$user, $site] = pitchSite();
    SitePitchVideo::factory()->generating()->create(['site_id' => $site->id]);

    $this->actingAs($user)
        ->post(pitchRoute('sites.pitch-video.store', $user, $site), [
            'presenter_id' => 'v2_public_adam@def',
            'voice_id' => 'en-US-GuyNeural',
            'script' => 'Hi there, we built a brand new website for your business and it is ready to look at.',
        ])
        ->assertSessionHasErrors('presenter_id');

    Queue::assertNothingPushed();
});

test('the create job asks D-ID for a clip with the chosen voice and then starts polling', function () {
    config(['services.did.api_key' => 'test-key']);
    Queue::fake([RefreshPitchVideo::class]);
    Http::fake(['api.d-id.com/clips' => Http::response(['id' => 'clp_123', 'status' => 'created'], 201)]);
    $video = SitePitchVideo::factory()->create(['status' => PitchVideoStatus::Generating]);

    (new CreatePitchVideo($video))->handle(app(DidClient::class));

    Http::assertSent(fn ($request) => $request->url() === 'https://api.d-id.com/clips'
        && $request->hasHeader('Authorization', 'Basic test-key')
        && $request['presenter_id'] === 'v2_public_amber@Y5K02DLS4m'
        && $request['script']['provider']['voice_id'] === 'en-US-JennyNeural');

    expect($video->fresh()->did_clip_id)->toBe('clp_123');
    Queue::assertPushed(RefreshPitchVideo::class);
});

test('a finished clip is downloaded and stored', function () {
    config(['services.did.api_key' => 'test-key']);
    Storage::fake('public');
    Http::fake([
        'api.d-id.com/clips/clp_123' => Http::response(['status' => 'done', 'result_url' => 'https://cdn.example.com/result.mp4']),
        'cdn.example.com/result.mp4' => Http::response('video-bytes'),
    ]);
    $video = SitePitchVideo::factory()->generating()->create(['did_clip_id' => 'clp_123']);

    (new RefreshPitchVideo($video))->handle(app(DidClient::class));

    $video = $video->fresh();

    expect($video->status)->toBe(PitchVideoStatus::Ready)
        ->and($video->generated_at)->not->toBeNull();
    Storage::disk('public')->assertExists($video->video_path);
});

test('a clip D-ID could not render is marked failed with its reason', function () {
    config(['services.did.api_key' => 'test-key']);
    Http::fake(['api.d-id.com/clips/clp_123' => Http::response(['status' => 'error', 'error' => ['kind' => 'TextModerationError', 'description' => 'The script was blocked.']])]);
    $video = SitePitchVideo::factory()->generating()->create(['did_clip_id' => 'clp_123']);

    (new RefreshPitchVideo($video))->handle(app(DidClient::class));

    expect($video->fresh()->status)->toBe(PitchVideoStatus::Failed)
        ->and($video->fresh()->error)->toBe('The script was blocked.');
});

test('a clip that is still rendering is checked again later', function () {
    config(['services.did.api_key' => 'test-key']);
    Queue::fake([RefreshPitchVideo::class]);
    Http::fake(['api.d-id.com/clips/clp_123' => Http::response(['status' => 'started'])]);
    $video = SitePitchVideo::factory()->generating()->create(['did_clip_id' => 'clp_123']);

    (new RefreshPitchVideo($video))->handle(app(DidClient::class));

    expect($video->fresh()->status)->toBe(PitchVideoStatus::Generating);
    Queue::assertPushed(RefreshPitchVideo::class);
});

test('a clip that never finishes is given up on', function () {
    config(['services.did.api_key' => 'test-key']);
    Queue::fake([RefreshPitchVideo::class]);
    Http::fake(['api.d-id.com/clips/clp_123' => Http::response(['status' => 'started'])]);
    $video = SitePitchVideo::factory()->generating()->create([
        'did_clip_id' => 'clp_123',
        'generation_started_at' => now()->subMinutes(RefreshPitchVideo::GIVE_UP_MINUTES + 1),
    ]);

    (new RefreshPitchVideo($video))->handle(app(DidClient::class));

    expect($video->fresh()->status)->toBe(PitchVideoStatus::Failed);
    Queue::assertNothingPushed();
});

test('the watch page shows the video and records the first view on the lead', function () {
    Storage::fake('public');
    $video = SitePitchVideo::factory()->ready()->create();

    $this->get($video->watchUrl())
        ->assertOk()
        ->assertSee($video->videoUrl(), false)
        ->assertSee('See your website');

    $this->get($video->watchUrl())->assertOk();

    expect($video->fresh()->views_count)->toBe(2)
        ->and($video->site->lead->activities()->where('type', LeadActivityType::PitchVideoViewed)->count())->toBe(1);
});

test('the watch page is not found until the video exists', function () {
    $video = SitePitchVideo::factory()->generating()->create();

    $this->get($video->watchUrl())->assertNotFound();
});

test('removing the video deletes its file', function () {
    Storage::fake('public');
    [$user, $site] = pitchSite();
    $video = SitePitchVideo::factory()->ready()->create(['site_id' => $site->id]);
    Storage::disk('public')->put($video->video_path, 'video-bytes');

    $this->actingAs($user)
        ->delete(pitchRoute('sites.pitch-video.destroy', $user, $site))
        ->assertRedirect();

    expect(SitePitchVideo::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing($video->video_path);
});

test('the video link is a merge field only once the video is ready', function () {
    [$user, $site] = pitchSite();
    $merge = app(MergeFieldRenderer::class);

    expect($merge->values($site->lead->fresh(), $user)['video_url'])->toBe('');

    $video = SitePitchVideo::factory()->ready()->create(['site_id' => $site->id]);

    expect($merge->values($site->lead->fresh(), $user)['video_url'])->toBe($video->watchUrl());
});

test('the proposal email includes the sales video when the client has one', function () {
    $proposal = Proposal::factory()->withDefaultItems()->create()->load(['lead', 'team', 'items']);
    $site = Site::factory()->create(['lead_id' => $proposal->lead_id]);
    $video = SitePitchVideo::factory()->ready()->create([
        'site_id' => $site->id,
        'presenter_thumbnail_url' => 'https://cdn.example.com/amber.jpg',
    ]);

    $mailable = new ProposalEmail($proposal->fresh(['lead.site.pitchVideo', 'team', 'items']), 'William Victor', 'agency@example.com');

    $mailable->assertSeeInHtml($video->watchUrl());
    $mailable->assertSeeInHtml('https://cdn.example.com/amber.jpg');
    $mailable->assertSeeInHtml('Watch the video');
    $mailable->assertSeeInHtml('We also recorded a short video');
    $mailable->assertSeeInText($video->watchUrl());
});

test('the proposal email has no video block when there is no video', function () {
    $proposal = Proposal::factory()->withDefaultItems()->create()->load(['lead', 'team', 'items']);

    (new ProposalEmail($proposal, 'William Victor', 'agency@example.com'))
        ->assertDontSeeInHtml('Watch the video')
        ->assertDontSeeInHtml('We also recorded a short video');
});

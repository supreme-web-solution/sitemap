<?php

use App\Ai\Agents\SiteVisitorAssistant;
use App\Enums\LeadActivityType;
use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\Site;
use App\Models\SiteAssistant;
use App\Models\User;
use App\Notifications\SiteContactNotification;
use App\Services\Assistant\AssistantKnowledgeBuilder;
use App\Services\Simli\SimliFaces;
use App\Services\SiteBuilder\SiteRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Audio;

function assistantSite(array $leadAttributes = []): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create($leadAttributes);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    return [$user, $site];
}

function assistantRoute(string $name, User $user, Site $site): string
{
    return route($name, ['current_team' => $user->currentTeam->slug, 'site' => $site->id]);
}

function fakeAssistantPresenters(): void
{
    config(['services.did.api_key' => 'test-key']);

    Http::fake([
        'api.d-id.com/clips/presenters*' => Http::response(['presenters' => [
            ['presenter_id' => 'v2_public_amber@abc', 'name' => 'Amber', 'gender' => 'female', 'thumbnail_url' => 'https://cdn.example.com/amber.jpg'],
            ['presenter_id' => 'v2_public_adam@def', 'name' => 'Adam', 'gender' => 'male', 'thumbnail_url' => 'https://cdn.example.com/adam.jpg'],
        ]]),
        'api.d-id.com/voices*' => Http::response([]),
    ]);
}

function assistantSettings(array $overrides = []): array
{
    return [
        'enabled' => true,
        'name' => 'Maya',
        'presenter_id' => 'v2_public_amber@abc',
        'voice_id' => 'nova',
        'launcher_side' => 'right',
        'greeting' => null,
        'knowledge' => 'Business: Mike Handyman. We fix things.',
        ...$overrides,
    ];
}

test('the knowledge base is written from the listing and the website', function () {
    [, $site] = assistantSite([
        'name' => 'Handyman Mike',
        'category' => 'Handyman',
        'phone' => '(512) 555-0100',
        'address' => '12 Main St, Austin, TX',
        'rating' => 4.8,
        'reviews_count' => 120,
        'opening_hours' => ['Monday' => '8 AM to 5 PM'],
    ]);
    $site->update(['content' => [...$site->content, 'services' => [['title' => 'Furniture Assembly', 'description' => 'We build flat packs.']]]]);
    LeadReview::factory()->for($site->lead)->create(['rating' => 5, 'body' => 'Mike was quick and tidy.', 'author_name' => 'Sam']);

    $knowledge = app(AssistantKnowledgeBuilder::class)->build($site->fresh());

    expect($knowledge)
        ->toContain('Name: Handyman Mike')
        ->toContain('(512) 555-0100')
        ->toContain('Monday: 8 AM to 5 PM')
        ->toContain('Furniture Assembly: We build flat packs.')
        ->toContain('Google rating: 4.8 out of 5 from 120 reviews.')
        ->toContain('Mike was quick and tidy.');
});

test('the editor drafts the assistant before it has been saved', function () {
    fakeAssistantPresenters();
    [$user, $site] = assistantSite(['name' => 'Handyman Mike']);

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertInertia(fn ($page) => $page
            ->where('assistant.enabled', false)
            ->where('assistant.voiceId', 'nova')
            ->where('assistant.didConfigured', true)
            ->where('assistant.knowledge', fn ($knowledge) => str_contains($knowledge, 'Handyman Mike'))
            ->has('assistant.voices'));

    expect(SiteAssistant::count())->toBe(0);
});

test('the editor loads presenters when the assistant tab asks for them', function () {
    fakeAssistantPresenters();
    [$user, $site] = assistantSite();

    $url = route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]);

    $this->actingAs($user)->get($url)
        ->assertInertia(fn ($page) => $page
            ->reloadOnly('assistantOptions', fn ($reload) => $reload
                ->has('assistantOptions.presenters', 2)
                ->where('assistantOptions.presenters.0.name', 'Amber')));
});

test('saving switches the assistant on with a photoreal presenter and ai voice', function () {
    fakeAssistantPresenters();
    [$user, $site] = assistantSite();

    $this->actingAs($user)
        ->put(assistantRoute('sites.assistant.update', $user, $site), assistantSettings([
            'name' => 'Amber',
            'voice_id' => 'coral',
            'greeting' => 'Welcome!',
        ]))
        ->assertRedirect();

    $assistant = $site->fresh()->assistant;

    expect($assistant->enabled)->toBeTrue()
        ->and($assistant->name)->toBe('Amber')
        ->and($assistant->presenter_id)->toBe('v2_public_amber@abc')
        ->and($assistant->presenter_thumbnail_url)->toBe('https://cdn.example.com/amber.jpg')
        ->and($assistant->voice_id)->toBe('coral')
        ->and($assistant->voice_name)->toBe('Coral')
        ->and($assistant->greeting)->toBe('Welcome!');
});

test('switching on with an empty knowledge base writes one from the site', function () {
    fakeAssistantPresenters();
    [$user, $site] = assistantSite(['name' => 'Handyman Mike']);

    $this->actingAs($user)
        ->put(assistantRoute('sites.assistant.update', $user, $site), assistantSettings(['knowledge' => '']))
        ->assertRedirect();

    expect($site->fresh()->assistant->knowledge)->toContain('Handyman Mike');
});

test('an enabled assistant needs a presenter', function () {
    fakeAssistantPresenters();
    [$user, $site] = assistantSite();

    $this->actingAs($user)
        ->put(assistantRoute('sites.assistant.update', $user, $site), assistantSettings(['presenter_id' => null]))
        ->assertSessionHasErrors(['presenter_id']);
});

test('refreshing the knowledge replaces edits with a fresh draft', function () {
    [$user, $site] = assistantSite(['name' => 'Handyman Mike']);
    SiteAssistant::factory()->create(['site_id' => $site->id, 'knowledge' => 'Old notes']);

    $this->actingAs($user)
        ->post(assistantRoute('sites.assistant.knowledge', $user, $site))
        ->assertRedirect();

    expect($site->fresh()->assistant->knowledge)->toContain('Handyman Mike')->not->toContain('Old notes');
});

test('the script tag is only added to the website while the assistant is switched on', function () {
    [, $site] = assistantSite();
    $assistant = SiteAssistant::factory()->create(['site_id' => $site->id, 'public_key' => 'abc123']);

    $renderer = app(SiteRenderer::class);

    expect($renderer->render($site->fresh()))->not->toContain('widget/assistant.js');

    $assistant->update(['enabled' => true]);

    $html = $renderer->render($site->fresh());

    expect($html)
        ->toContain('/widget/assistant.js')
        ->toContain('data-assistant-key="abc123"')
        ->and($renderer->render($site->fresh(), asPreview: true, editor: true))->not->toContain('widget/assistant.js');
});

test('the exported zip carries the assistant script only when it is enabled', function () {
    [$user, $site] = assistantSite();
    $assistant = SiteAssistant::factory()->create(['site_id' => $site->id, 'public_key' => 'zipkey']);

    $read = function () use ($user, $site): string {
        $response = $this->actingAs($user)->get(route('sites.export', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]));
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $html = $zip->getFromName('index.html');
        $zip->close();

        return $html;
    };

    expect($read())->not->toContain('data-assistant-key');

    $assistant->update(['enabled' => true]);

    expect($read())->toContain('data-assistant-key="zipkey"');
});

test('the widget loads its photoreal presenter settings with cors headers', function () {
    [, $site] = assistantSite(['name' => 'Handyman Mike']);
    SiteAssistant::factory()->enabled()->create([
        'site_id' => $site->id,
        'public_key' => 'livekey',
        'name' => 'Amber',
        'voice_id' => 'nova',
    ]);

    $this->getJson(route('assistant.config', 'livekey'))
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonPath('name', 'Amber')
        ->assertJsonPath('voice_on', true)
        ->assertJsonPath('launcher.side', 'right')
        ->assertJsonPath('presenter.image_url', 'https://cdn.example.com/amber.jpg')
        ->assertJsonPath('greeting', fn ($greeting) => str_contains($greeting, 'Handyman Mike'));
});

test('saving can place the launcher on the left', function () {
    fakeAssistantPresenters();
    [$user, $site] = assistantSite();

    $this->actingAs($user)
        ->put(assistantRoute('sites.assistant.update', $user, $site), assistantSettings([
            'launcher_side' => 'left',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $assistant = $site->fresh()->assistant;

    expect($assistant->launcher_side)->toBe('left');

    $this->getJson(route('assistant.config', $assistant->public_key))
        ->assertOk()
        ->assertJsonPath('launcher.side', 'left');
});

test('the widget gets nothing once the assistant is switched off or unknown', function () {
    [, $site] = assistantSite();
    SiteAssistant::factory()->create(['site_id' => $site->id, 'public_key' => 'offkey']);

    $this->getJson(route('assistant.config', 'offkey'))->assertNotFound();
    $this->postJson(route('assistant.chat', 'offkey'), ['message' => 'Hi'])->assertNotFound();
    $this->getJson(route('assistant.config', 'nope'))->assertNotFound();
});

test('browsers can preflight the widget requests', function () {
    $this->call('OPTIONS', '/assistant/livekey/chat')
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Accept');
});

test('a visitor message is answered with text right away so voice can load separately', function () {
    config(['services.anthropic.key' => 'test-key', 'services.openai.key' => 'openai-key']);
    SiteVisitorAssistant::fake([['reply' => 'We are open Monday at eight.', 'ask_for_contact' => false]]);
    Audio::fake(['ZmFrZS1hdWRpby1ieXRlcw==']);
    [, $site] = assistantSite();
    $assistant = SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'chatkey']);

    $this->postJson(route('assistant.chat', 'chatkey'), [
        'message' => 'When do you open?',
        'history' => [
            ['role' => 'assistant', 'content' => 'Hi there'],
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'How can I help?'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('reply', 'We are open Monday at eight.')
        ->assertJsonPath('ask_for_contact', false)
        ->assertJsonPath('audio', null)
        ->assertJsonPath('audio_mime', null);

    expect($assistant->fresh()->messages_count)->toBe(1);

    $this->postJson(route('assistant.speak', 'chatkey'), ['text' => 'We are open Monday at eight.'])
        ->assertOk()
        ->assertJsonPath('audio', 'ZmFrZS1hdWRpby1ieXRlcw==')
        ->assertJsonPath('audio_mime', 'audio/mpeg');
});

test('the assistant offers the contact form when the visitor is ready to book', function () {
    config(['services.anthropic.key' => 'test-key']);
    SiteVisitorAssistant::fake([['reply' => 'Happy to help. Leave your details.', 'ask_for_contact' => true]]);
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'bookkey', 'voice_id' => 'off']);

    $this->postJson(route('assistant.chat', 'bookkey'), ['message' => 'I want a quote'])
        ->assertOk()
        ->assertJsonPath('ask_for_contact', true)
        ->assertJsonPath('audio', null);
});

test('a chat message needs text', function () {
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'emptykey']);

    $this->postJson(route('assistant.chat', 'emptykey'), ['message' => ''])->assertUnprocessable();
});

test('the visitor is asked for their details when the ai is unavailable', function () {
    config(['services.anthropic.key' => null, 'ai.providers.anthropic.key' => null]);
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'nokey', 'voice_id' => 'off']);

    $this->postJson(route('assistant.chat', 'nokey'), ['message' => 'Hi'])
        ->assertOk()
        ->assertJsonPath('ask_for_contact', true);
});

test('a website that used its daily allowance falls back to taking details', function () {
    config(['services.anthropic.key' => 'test-key', 'assistant.daily_message_cap' => 1]);
    SiteVisitorAssistant::fake([['reply' => 'First answer.', 'ask_for_contact' => false]]);
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'capkey', 'voice_id' => 'off']);
    Cache::flush();

    $this->postJson(route('assistant.chat', 'capkey'), ['message' => 'One'])->assertJsonPath('reply', 'First answer.');
    $this->postJson(route('assistant.chat', 'capkey'), ['message' => 'Two'])
        ->assertOk()
        ->assertJsonPath('ask_for_contact', true)
        ->assertJsonPath('reply', fn ($reply) => $reply !== 'First answer.');
});

test('details left in the widget reach the business like a contact form message', function () {
    Notification::fake();
    [$user, $site] = assistantSite();
    $assistant = SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'leadkey']);

    $this->postJson(route('assistant.lead', 'leadkey'), [
        'name' => 'Jane Visitor',
        'contact' => 'jane@example.com',
        'message' => 'Need a quote',
        'history' => [
            ['role' => 'user', 'content' => 'Do you fix doors?'],
            ['role' => 'assistant', 'content' => 'Yes we do.'],
        ],
    ])->assertOk()->assertJson(['ok' => true]);

    $activity = $site->lead->activities()->where('type', LeadActivityType::PreviewInterest)->first();

    expect($activity->data['name'])->toBe('Jane Visitor')
        ->and($activity->data['message'])->toContain('Need a quote')->toContain('Visitor: Do you fix doors?')
        ->and($assistant->fresh()->leads_count)->toBe(1);

    Notification::assertSentTo($user->currentTeam->owner(), SiteContactNotification::class);
});

test('details are required and bots are ignored', function () {
    Notification::fake();
    [, $site] = assistantSite();
    $assistant = SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'botkey']);

    $this->postJson(route('assistant.lead', 'botkey'), ['name' => '', 'contact' => ''])->assertUnprocessable();

    $this->postJson(route('assistant.lead', 'botkey'), ['name' => 'Bot', 'contact' => 'bot@example.com', 'website' => 'http://spam.test'])->assertOk();

    expect($assistant->fresh()->leads_count)->toBe(0);
    Notification::assertNothingSent();
});

test('the talking avatar toggle stays hidden until the platform flag and simli key are set', function () {
    fakeAssistantPresenters();
    config(['assistant.talking_avatar_available' => false, 'services.simli.api_key' => null]);
    [$user, $site] = assistantSite();

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertInertia(fn ($page) => $page
            ->where('assistant.talkingAvatarAvailable', false)
            ->where('assistant.simliFaces', []));
});

test('the editor shows talking faces when the platform enables them', function () {
    fakeAssistantPresenters();
    config(['assistant.talking_avatar_available' => true, 'services.simli.api_key' => 'simli-test']);
    [$user, $site] = assistantSite();

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertInertia(fn ($page) => $page
            ->where('assistant.talkingAvatarAvailable', true)
            ->has('assistant.simliFaces')
            ->where('assistant.simliFaces.0.id', SimliFaces::DEFAULT));
});

test('saving can turn on the talking avatar with a simli face', function () {
    fakeAssistantPresenters();
    config(['assistant.talking_avatar_available' => true, 'services.simli.api_key' => 'simli-test']);
    [$user, $site] = assistantSite();

    $this->actingAs($user)
        ->put(assistantRoute('sites.assistant.update', $user, $site), assistantSettings([
            'talking_avatar_enabled' => true,
            'simli_face_id' => SimliFaces::DEFAULT,
            'presenter_id' => null,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $assistant = $site->fresh()->assistant;

    expect($assistant->talking_avatar_enabled)->toBeTrue()
        ->and($assistant->simli_face_id)->toBe(SimliFaces::DEFAULT)
        ->and($assistant->simli_face_name)->toBe('Anna');
});

test('the widget config exposes talking avatar only when it is active', function () {
    config(['assistant.talking_avatar_available' => true, 'services.simli.api_key' => 'simli-test']);
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create([
        'site_id' => $site->id,
        'public_key' => 'talkkey',
        'talking_avatar_enabled' => true,
        'simli_face_id' => SimliFaces::DEFAULT,
        'simli_face_name' => 'Anna',
    ]);

    $this->getJson(route('assistant.config', 'talkkey'))
        ->assertOk()
        ->assertHeaderContains('Cache-Control', 'no-cache')
        ->assertHeaderContains('Cache-Control', 'must-revalidate')
        ->assertJsonPath('talking_avatar.enabled', true)
        ->assertJsonPath('talking_avatar.face_id', SimliFaces::DEFAULT)
        ->assertJsonPath('talking_avatar.session_path', '/session')
        ->assertJsonPath('presenter.name', 'Anna')
        ->assertJsonPath(
            'presenter.image_url',
            'https://mintcdn.com/simli/C8pXSe7OQG-XmRnX/images/trinity2/'.SimliFaces::DEFAULT.'.jpg',
        );
});

test('the widget falls back to still mode when the admin kill switch is off', function () {
    config(['assistant.talking_avatar_available' => false, 'services.simli.api_key' => 'simli-test']);
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create([
        'site_id' => $site->id,
        'public_key' => 'stillkey',
        'talking_avatar_enabled' => true,
        'simli_face_id' => SimliFaces::DEFAULT,
    ]);

    $this->getJson(route('assistant.config', 'stillkey'))
        ->assertOk()
        ->assertJsonPath('talking_avatar.enabled', false)
        ->assertJsonPath('talking_avatar.face_id', null);

    $this->postJson(route('assistant.session', 'stillkey'))->assertNotFound();
});

test('the widget can mint a simli session token without exposing the api key', function () {
    config(['assistant.talking_avatar_available' => true, 'services.simli.api_key' => 'simli-secret']);
    Http::fake([
        'api.simli.ai/compose/token' => Http::response(['session_token' => 'sess_abc']),
        'api.simli.ai/compose/ice' => Http::response([['urls' => ['stun:stun.l.google.com:19302']]]),
    ]);
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create([
        'site_id' => $site->id,
        'public_key' => 'sesskey',
        'talking_avatar_enabled' => true,
        'simli_face_id' => SimliFaces::DEFAULT,
    ]);

    $this->postJson(route('assistant.session', 'sesskey'))
        ->assertOk()
        ->assertJsonPath('session_token', 'sess_abc')
        ->assertJsonMissing(['api_key' => 'simli-secret']);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.simli.ai/compose/token'
        && $request->hasHeader('x-simli-api-key', 'simli-secret')
        && $request['faceId'] === SimliFaces::DEFAULT);
});

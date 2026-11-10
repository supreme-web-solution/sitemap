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
use App\Services\SiteBuilder\SiteRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

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

function assistantSettings(array $overrides = []): array
{
    return [
        'enabled' => true,
        'name' => 'Maya',
        'avatar' => 'maya',
        'voice' => 'female',
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
    [$user, $site] = assistantSite(['name' => 'Handyman Mike']);

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertInertia(fn ($page) => $page
            ->where('assistant.enabled', false)
            ->where('assistant.avatar', 'maya')
            ->where('assistant.voice', 'female')
            ->where('assistant.knowledge', fn ($knowledge) => str_contains($knowledge, 'Handyman Mike'))
            ->has('assistant.avatars', 6));

    expect(SiteAssistant::count())->toBe(0);
});

test('saving switches the assistant on and keeps the owner edits', function () {
    [$user, $site] = assistantSite();

    $this->actingAs($user)
        ->put(assistantRoute('sites.assistant.update', $user, $site), assistantSettings(['name' => 'Maya B', 'greeting' => 'Welcome!']))
        ->assertRedirect();

    $assistant = $site->fresh()->assistant;

    expect($assistant->enabled)->toBeTrue()
        ->and($assistant->name)->toBe('Maya B')
        ->and($assistant->greeting)->toBe('Welcome!')
        ->and($assistant->knowledge)->toBe('Business: Mike Handyman. We fix things.')
        ->and($assistant->public_key)->toHaveLength(40);
});

test('switching on with an empty knowledge base writes one from the site', function () {
    [$user, $site] = assistantSite(['name' => 'Handyman Mike']);

    $this->actingAs($user)
        ->put(assistantRoute('sites.assistant.update', $user, $site), assistantSettings(['knowledge' => '']))
        ->assertRedirect();

    expect($site->fresh()->assistant->knowledge)->toContain('Handyman Mike');
});

test('refreshing the knowledge replaces edits with a fresh draft', function () {
    [$user, $site] = assistantSite(['name' => 'Handyman Mike']);
    SiteAssistant::factory()->create(['site_id' => $site->id, 'knowledge' => 'Old notes']);

    $this->actingAs($user)
        ->post(assistantRoute('sites.assistant.knowledge', $user, $site))
        ->assertRedirect();

    expect($site->fresh()->assistant->knowledge)->toContain('Handyman Mike')->not->toContain('Old notes');
});

test('the assistant settings are validated', function () {
    [$user, $site] = assistantSite();

    $this->actingAs($user)
        ->put(assistantRoute('sites.assistant.update', $user, $site), assistantSettings(['avatar' => 'robot', 'voice' => 'shout', 'name' => '']))
        ->assertSessionHasErrors(['avatar', 'voice', 'name']);
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

test('the widget loads its settings from any website with cors headers', function () {
    [, $site] = assistantSite(['name' => 'Handyman Mike']);
    SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'livekey', 'name' => 'Maya', 'greeting' => null]);

    $this->getJson(route('assistant.config', 'livekey'))
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertJsonPath('name', 'Maya')
        ->assertJsonPath('voice', 'female')
        ->assertJsonPath('greeting', fn ($greeting) => str_contains($greeting, 'Handyman Mike'))
        ->assertJsonPath('avatar_url', route('assistant.avatar', 'maya'));
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

test('avatars are served as svg images', function () {
    $this->get(route('assistant.avatar', 'priya'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertSee('id="sm-mouth"', false);

    $this->get('/assistant/avatars/robot.svg')->assertNotFound();
});

test('a visitor message is answered from the knowledge base', function () {
    config(['services.anthropic.key' => 'test-key']);
    SiteVisitorAssistant::fake([['reply' => 'We are open Monday at eight.', 'ask_for_contact' => false]]);
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
        ->assertExactJson(['reply' => 'We are open Monday at eight.', 'ask_for_contact' => false]);

    expect($assistant->fresh()->messages_count)->toBe(1);
});

test('the assistant offers the contact form when the visitor is ready to book', function () {
    config(['services.anthropic.key' => 'test-key']);
    SiteVisitorAssistant::fake([['reply' => 'Happy to help. Leave your details.', 'ask_for_contact' => true]]);
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'bookkey']);

    $this->postJson(route('assistant.chat', 'bookkey'), ['message' => 'I want a quote'])
        ->assertOk()
        ->assertJsonPath('ask_for_contact', true);
});

test('a chat message needs text', function () {
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'emptykey']);

    $this->postJson(route('assistant.chat', 'emptykey'), ['message' => ''])->assertUnprocessable();
});

test('the visitor is asked for their details when the ai is unavailable', function () {
    config(['services.anthropic.key' => null, 'ai.providers.anthropic.key' => null]);
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'nokey']);

    $this->postJson(route('assistant.chat', 'nokey'), ['message' => 'Hi'])
        ->assertOk()
        ->assertJsonPath('ask_for_contact', true);
});

test('a website that used its daily allowance falls back to taking details', function () {
    config(['services.anthropic.key' => 'test-key', 'assistant.daily_message_cap' => 1]);
    SiteVisitorAssistant::fake([['reply' => 'First answer.', 'ask_for_contact' => false]]);
    [, $site] = assistantSite();
    SiteAssistant::factory()->enabled()->create(['site_id' => $site->id, 'public_key' => 'capkey']);
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

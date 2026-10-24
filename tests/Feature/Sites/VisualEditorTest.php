<?php

use App\Jobs\ApplySiteEdit;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteBuilder\SiteEditApplier;
use App\Services\SiteBuilder\SiteRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: Site, 1: User}
 */
function editorSite(): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['phone' => '555-0100']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    return [$site, $user];
}

/**
 * @param  list<array<string, mixed>>  $ops
 */
function sendEdits(User $user, Site $site, array $ops, ?int $revision = null)
{
    return test()->actingAs($user)->postJson(
        route('sites.edits.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]),
        ['revision' => $revision ?? $site->fresh()->revision, 'ops' => $ops],
    );
}

test('a set operation updates the site and moves the revision on', function () {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [['op' => 'set', 'path' => 'headline', 'value' => 'Brand new headline']])
        ->assertOk()
        ->assertJsonPath('revision', 1)
        ->assertJsonPath('document.headline', 'Brand new headline')
        ->assertJsonStructure(['quality' => ['checks']]);

    expect($site->fresh())->revision->toBe(1)
        ->and($site->fresh()->content['headline'])->toBe('Brand new headline');
});

test('edits made close together share one editing session version', function () {
    [$site, $user] = editorSite();
    $original = $site->content['headline'];

    sendEdits($user, $site, [['op' => 'set', 'path' => 'headline', 'value' => 'First change']])->assertOk();
    sendEdits($user, $site, [['op' => 'set', 'path' => 'headline', 'value' => 'Second change']])->assertOk();

    $version = $site->versions()->sole();

    expect($version->label)->toBe('Visual editing session')
        ->and($version->content['headline'])->toBe($original)
        ->and($version->user_id)->toBe($user->id);

    $this->travel(20)->minutes();

    sendEdits($user, $site, [['op' => 'set', 'path' => 'headline', 'value' => 'Third change']])->assertOk();

    expect($site->versions()->count())->toBe(2);
});

test('an edit that changes nothing does not bump the revision or save a version', function () {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [['op' => 'set', 'path' => 'headline', 'value' => $site->content['headline']]])
        ->assertOk()
        ->assertJsonPath('revision', 0);

    expect($site->versions()->count())->toBe(0);
});

test('a stale revision is refused and nothing is changed', function () {
    [$site, $user] = editorSite();
    $site->update(['content' => [...$site->content, 'headline' => 'Changed by someone else']]);

    sendEdits($user, $site, [['op' => 'set', 'path' => 'headline', 'value' => 'My change']], revision: 0)
        ->assertStatus(409)
        ->assertJsonPath('revision', 1);

    expect($site->fresh()->content['headline'])->toBe('Changed by someone else');
});

test('only allowlisted paths can be set', function (string $path) {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [['op' => 'set', 'path' => $path, 'value' => 'x']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ops.0');

    expect($site->fresh()->revision)->toBe(0);
})->with([
    'a column' => 'team_id',
    'a hidden field' => 'preview_password',
    'an unknown service field' => 'services.0.owner',
    'a whole list' => 'services',
    'a nested unknown key' => 'copy.nested.key',
]);

test('the whole document is validated before anything is stored', function () {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [['op' => 'set', 'path' => 'headline', 'value' => str_repeat('x', 400)]])
        ->assertUnprocessable();

    sendEdits($user, $site, [['op' => 'set', 'path' => 'accent', 'value' => 'not-a-colour']])
        ->assertUnprocessable();

    sendEdits($user, $site, [['op' => 'set', 'path' => 'design.font', 'value' => 'comic-sans']])
        ->assertUnprocessable();

    expect($site->fresh())->revision->toBe(0)
        ->and($site->fresh()->content['headline'])->toBe($site->content['headline']);
});

test('a batch is all or nothing', function () {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [
        ['op' => 'set', 'path' => 'headline', 'value' => 'Fine'],
        ['op' => 'remove', 'path' => 'services', 'index' => 5],
    ])->assertUnprocessable();

    expect($site->fresh()->content['headline'])->not->toBe('Fine');
});

test('too many operations in one request are refused', function () {
    [$site, $user] = editorSite();
    $ops = array_fill(0, SiteEditApplier::MAX_OPERATIONS + 1, ['op' => 'set', 'path' => 'headline', 'value' => 'Same']);

    sendEdits($user, $site, $ops)->assertUnprocessable();
});

test('sections can be moved, hidden and shown again', function () {
    [$site, $user] = editorSite();
    $order = $site->content['sections'];
    $first = $order[0];

    sendEdits($user, $site, [['op' => 'move', 'path' => 'sections', 'from' => 0, 'to' => 1]])
        ->assertOk()
        ->assertJsonPath('document.sections.1', $first);

    sendEdits($user, $site, [['op' => 'remove', 'path' => 'sections', 'index' => 1]])->assertOk();

    expect($site->fresh()->content['sections'])->not->toContain($first);

    sendEdits($user, $site, [['op' => 'insert', 'path' => 'sections', 'index' => 0, 'value' => $first]])
        ->assertOk()
        ->assertJsonPath('document.sections.0', $first);
});

test('services and faqs can be added, edited and removed', function () {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [
        ['op' => 'insert', 'path' => 'services', 'index' => 1, 'value' => ['title' => 'Boiler service', 'description' => 'Yearly checks.', 'slug' => 'boilers']],
        ['op' => 'insert', 'path' => 'faqs', 'index' => 0, 'value' => ['question' => 'Do you quote?', 'answer' => 'Yes, free.']],
        ['op' => 'set', 'path' => 'services.1.description', 'value' => 'Yearly checks and repairs.'],
    ])->assertOk();

    $content = $site->fresh()->content;

    expect($content['services'])->toHaveCount(2)
        ->and($content['services'][1]['description'])->toBe('Yearly checks and repairs.')
        ->and($content['faqs'][0]['question'])->toBe('Do you quote?');

    sendEdits($user, $site, [['op' => 'remove', 'path' => 'services', 'index' => 0]])->assertOk();

    expect($site->fresh()->content['services'][0]['title'])->toBe('Boiler service');
});

test('a site cannot be left without services', function () {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [['op' => 'remove', 'path' => 'services', 'index' => 0]])->assertUnprocessable();

    expect($site->fresh()->content['services'])->toHaveCount(1);
});

test('reworded labels and design choices are stored and rendered', function () {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [
        ['op' => 'set', 'path' => 'copy.cta_heading', 'value' => 'Book your visit today'],
        ['op' => 'set', 'path' => 'copy.about_title', 'value' => 'Who we are'],
        ['op' => 'set', 'path' => 'design.font', 'value' => 'playfair'],
        ['op' => 'set', 'path' => 'design.radius', 'value' => 'round'],
        ['op' => 'set', 'path' => 'section_styles.about', 'value' => 'dark'],
    ])->assertOk();

    $html = app(SiteRenderer::class)->render($site->fresh());

    expect($html)->toContain('Book your visit today')
        ->toContain('Who we are')
        ->toContain('Playfair Display')
        ->toContain('data-sec-style="dark"')
        ->not->toContain('data-edit');
});

test('a label can be put back to the template default', function () {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [['op' => 'set', 'path' => 'copy.about_title', 'value' => 'Who we are']])->assertOk();
    sendEdits($user, $site, [['op' => 'set', 'path' => 'copy.about_title', 'value' => null]])->assertOk();

    expect($site->fresh()->content)->not->toHaveKey('copy.about_title')
        ->and($site->fresh()->content['copy'] ?? [])->not->toHaveKey('about_title');
});

test('the old save form and ai edits keep the editor-only fields', function () {
    [$site, $user] = editorSite();

    sendEdits($user, $site, [
        ['op' => 'set', 'path' => 'copy.about_title', 'value' => 'Who we are'],
        ['op' => 'set', 'path' => 'design.spacing', 'value' => 'compact'],
    ])->assertOk();

    $site->refresh();

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'template' => $site->template->value,
            'accent' => $site->content['accent'],
            'headline' => 'Edited in the old form',
            'tagline' => $site->content['tagline'],
            'cta_label' => $site->content['cta_label'],
            'about' => $site->content['about'],
            'sections' => $site->content['sections'],
            'services' => $site->content['services'],
            'faqs' => [],
        ])
        ->assertRedirect();

    $content = $site->fresh()->content;

    expect($content['headline'])->toBe('Edited in the old form')
        ->and($content['copy']['about_title'])->toBe('Who we are')
        ->and($content['design']['spacing'])->toBe('compact');
});

test('another team cannot edit the site', function () {
    [$site] = editorSite();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson(route('sites.edits.store', ['current_team' => $stranger->currentTeam->slug, 'site' => $site->id]), [
            'revision' => 0,
            'ops' => [['op' => 'set', 'path' => 'headline', 'value' => 'Hijacked']],
        ])
        ->assertNotFound();

    expect($site->fresh()->content['headline'])->not->toBe('Hijacked');
});

test('guests cannot edit', function () {
    [$site, $user] = editorSite();

    $this->postJson(route('sites.edits.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
        'revision' => 0,
        'ops' => [],
    ])->assertUnauthorized();
});

test('team members get editable markup in the editor preview and nobody else does', function () {
    [$site, $user] = editorSite();
    $editorUrl = route('sites.preview', ['site' => $site->slug, 'internal' => 1, 'editor' => 1]);

    $response = $this->actingAs($user)->get($editorUrl)->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())
        ->toContain('data-edit="headline"')
        ->toContain('data-edit="tagline"')
        ->toContain('data-section="about"');

});

test('visitors never get editable markup, even when they ask for it', function () {
    [$site] = editorSite();

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1, 'editor' => 1]))
        ->assertOk()
        ->assertDontSee('data-edit=', false)
        ->assertDontSee('data-section=', false);

    $this->get(route('sites.preview', $site->slug))->assertOk()->assertDontSee('data-edit=', false);
});

test('a member of another team gets the plain preview', function () {
    [$site] = editorSite();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1, 'editor' => 1]))
        ->assertOk()
        ->assertDontSee('data-edit=', false);
});

test('services pages carry the right content path for editing', function () {
    [$site, $user] = editorSite();
    $slug = SiteEditApplier::document($site)['services'][0]['title'];

    $html = app(SiteRenderer::class)->render($site, true, 'services', null, true);

    expect($html)->toContain('data-edit="services.0.title"')
        ->and($html)->toContain($slug);
});

test('the editor page exposes what the visual editor needs', function () {
    [$site, $user] = editorSite();

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertInertia(fn ($page) => $page
            ->where('site.revision', 0)
            ->where('site.editorUrl', fn (string $url) => str_contains($url, 'editor=1') && str_contains($url, 'internal=1'))
            ->has('site.media.photos')
            ->has('designChoices.font')
            ->has('copyFields'));
});

test('the last visual editor image change can be undone', function () {
    Storage::fake('public');
    [$site, $user] = editorSite();
    $params = ['current_team' => $user->currentTeam->slug, 'site' => $site->id];
    $storeUrl = route('sites.media.store', $params);
    $undoUrl = route('sites.media.undo', $params);

    $this->actingAs($user)
        ->post($storeUrl, ['slot' => 'photo', 'image' => UploadedFile::fake()->image('first.jpg', 800, 600)])
        ->assertOk()
        ->assertJsonCount(1, 'photos');

    $pathAfterFirst = $site->lead->fresh()->photo_paths[0];

    $this->actingAs($user)
        ->post($storeUrl, ['slot' => 'photo', 'index' => 0, 'image' => UploadedFile::fake()->image('second.jpg', 800, 600)])
        ->assertOk();

    $pathAfterSecond = $site->lead->fresh()->photo_paths[0];
    expect($pathAfterSecond)->not->toBe($pathAfterFirst);

    $this->actingAs($user)
        ->post($undoUrl)
        ->assertOk()
        ->assertJsonCount(1, 'photos');

    $pathAfterUndo = $site->lead->fresh()->photo_paths[0];
    expect($pathAfterUndo)->not->toBe($pathAfterSecond);
    Storage::disk('public')->assertExists($pathAfterUndo);

    $this->actingAs($user)->post($undoUrl)->assertNotFound();
});

test('a photo can be replaced, added and removed', function () {
    Storage::fake('public');
    [$site, $user] = editorSite();
    $route = fn (string $name) => route($name, ['current_team' => $user->currentTeam->slug, 'site' => $site->id]);

    $this->actingAs($user)
        ->post($route('sites.media.store'), ['slot' => 'photo', 'image' => UploadedFile::fake()->image('a.jpg', 800, 600)])
        ->assertOk()
        ->assertJsonCount(1, 'photos');

    $this->actingAs($user)
        ->post($route('sites.media.store'), ['slot' => 'photo', 'image' => UploadedFile::fake()->image('b.jpg', 800, 600)])
        ->assertOk()
        ->assertJsonCount(2, 'photos');

    $first = $site->lead->fresh()->photo_paths[0];

    $this->actingAs($user)
        ->post($route('sites.media.store'), ['slot' => 'photo', 'index' => 0, 'image' => UploadedFile::fake()->image('c.jpg', 800, 600)])
        ->assertOk()
        ->assertJsonCount(2, 'photos');

    Storage::disk('public')->assertMissing($first);

    $this->actingAs($user)
        ->deleteJson($route('sites.media.destroy'), ['slot' => 'photo', 'index' => 1])
        ->assertOk()
        ->assertJsonCount(1, 'photos');
});

test('the logo can be replaced and removed', function () {
    Storage::fake('public');
    [$site, $user] = editorSite();
    $params = ['current_team' => $user->currentTeam->slug, 'site' => $site->id];

    $this->actingAs($user)
        ->post(route('sites.media.store', $params), ['slot' => 'logo', 'image' => UploadedFile::fake()->image('logo.png', 300, 300)])
        ->assertOk()
        ->assertJsonPath('logo', fn (?string $url) => $url !== null);

    $path = $site->lead->fresh()->logo_path;
    Storage::disk('public')->assertExists($path);

    $this->actingAs($user)
        ->deleteJson(route('sites.media.destroy', $params), ['slot' => 'logo'])
        ->assertOk()
        ->assertJsonPath('logo', null);

    Storage::disk('public')->assertMissing($path);
});

test('uploads must be real images of a sensible size', function () {
    Storage::fake('public');
    [$site, $user] = editorSite();
    $url = route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]);

    $this->actingAs($user)
        ->postJson($url, ['slot' => 'photo', 'image' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->postJson($url, ['slot' => 'logo', 'image' => UploadedFile::fake()->image('huge.png')->size(4000)])
        ->assertUnprocessable();
});

test('another team cannot change a site\'s photos', function () {
    Storage::fake('public');
    [$site] = editorSite();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson(route('sites.media.store', ['current_team' => $stranger->currentTeam->slug, 'site' => $site->id]), [
            'slot' => 'photo',
            'image' => UploadedFile::fake()->image('a.jpg'),
        ])
        ->assertNotFound();
});

test('ai can be asked to improve one piece of text', function () {
    Queue::fake();
    [$site, $user] = editorSite();
    $params = ['current_team' => $user->currentTeam->slug, 'site' => $site->id];

    $this->actingAs($user)
        ->post(route('sites.chat.store', $params), ['field' => 'headline', 'body' => 'Make it friendlier'])
        ->assertRedirect();

    Queue::assertPushed(ApplySiteEdit::class);

    $message = $site->chatMessages()->where('role', 'user')->sole();

    expect($message->body)->toContain('headline')
        ->toContain($site->content['headline'])
        ->toContain('Make it friendlier');
});

test('ai cannot be pointed at a part of the site that is not editable', function () {
    Queue::fake();
    [$site, $user] = editorSite();

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), ['field' => 'team_id'])
        ->assertSessionHasErrors('field');

    Queue::assertNothingPushed();
});

test('authenticated edit flow loads instrumented preview and round-trips saves with revision conflicts', function () {
    [$site, $user] = editorSite();
    $team = $user->currentTeam->slug;
    $preview = route('sites.preview', ['site' => $site->slug, 'internal' => 1, 'editor' => 1]);

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $team, 'site' => $site->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('site.revision', 0)
            ->where('site.editorUrl', fn (string $url) => str_contains($url, 'editor=1')));

    $this->actingAs($user)
        ->get($preview)
        ->assertOk()
        ->assertSee('data-edit="headline"', false);

    sendEdits($user, $site, [['op' => 'set', 'path' => 'headline', 'value' => 'Checklist headline']])
        ->assertOk()
        ->assertJsonPath('revision', 1)
        ->assertJsonPath('document.headline', 'Checklist headline');

    $this->actingAs($user)
        ->get($preview)
        ->assertOk()
        ->assertSee('Checklist headline', false);

    sendEdits($user, $site, [['op' => 'set', 'path' => 'headline', 'value' => 'Stale tab']], revision: 0)
        ->assertStatus(409)
        ->assertJsonPath('revision', 1);

    expect($site->fresh()->content['headline'])->toBe('Checklist headline');
});

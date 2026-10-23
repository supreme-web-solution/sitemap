<?php

use App\Models\Site;
use App\Models\SitePost;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: Site, 1: User}
 */
function blogSiteWithOwner(): array
{
    [$site, $user] = editableSite();

    return [$site, $user];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function postPayload(array $overrides = []): array
{
    return [
        'title' => 'Winter pipe tips',
        'excerpt' => 'Keep your pipes from freezing.',
        'body' => "Lag your pipes.\n\nTurn off outdoor taps.",
        'is_published' => true,
        ...$overrides,
    ];
}

test('a post can be added and is published straight away', function () {
    [$site, $user] = blogSiteWithOwner();

    $this->actingAs($user)
        ->post(route('sites.posts.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), postPayload())
        ->assertRedirect();

    $post = $site->posts()->sole();

    expect($post->slug)->toBe('winter-pipe-tips')
        ->and($post->published_at)->not->toBeNull();
});

test('a post can be saved as a draft', function () {
    [$site, $user] = blogSiteWithOwner();

    $this->actingAs($user)
        ->post(route('sites.posts.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), postPayload(['is_published' => false]))
        ->assertRedirect();

    expect($site->posts()->sole()->published_at)->toBeNull()
        ->and($site->posts()->published()->count())->toBe(0);
});

test('post slugs stay unique within a site', function () {
    [$site, $user] = blogSiteWithOwner();
    $url = route('sites.posts.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]);

    $this->actingAs($user)->post($url, postPayload());
    $this->actingAs($user)->post($url, postPayload());

    expect($site->posts()->orderBy('id')->pluck('slug')->all())->toBe(['winter-pipe-tips', 'winter-pipe-tips-2']);
});

test('editing a post keeps its address and its original publish date', function () {
    [$site, $user] = blogSiteWithOwner();
    $post = SitePost::factory()->for($site)->create(['slug' => 'winter-pipe-tips', 'published_at' => now()->subWeek()]);
    $published = $post->published_at;

    $this->actingAs($user)
        ->put(route('sites.posts.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id, 'post' => $post->id]), postPayload(['title' => 'A better title']))
        ->assertRedirect();

    $post->refresh();

    expect($post->title)->toBe('A better title')
        ->and($post->slug)->toBe('winter-pipe-tips')
        ->and($post->published_at->equalTo($published))->toBeTrue();
});

test('a post can be unpublished and deleted', function () {
    [$site, $user] = blogSiteWithOwner();
    $post = SitePost::factory()->for($site)->create();
    $params = ['current_team' => $user->currentTeam->slug, 'site' => $site->id, 'post' => $post->id];

    $this->actingAs($user)->put(route('sites.posts.update', $params), postPayload(['is_published' => false]));
    expect($post->fresh()->published_at)->toBeNull();

    $this->actingAs($user)->delete(route('sites.posts.destroy', $params))->assertRedirect();
    expect(SitePost::count())->toBe(0);
});

test('a post needs a title and body', function () {
    [$site, $user] = blogSiteWithOwner();

    $this->actingAs($user)
        ->post(route('sites.posts.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), postPayload(['title' => '', 'body' => '', 'slug' => 'Bad Slug']))
        ->assertSessionHasErrors(['title', 'body', 'slug']);
});

test('posts on another team’s site cannot be changed', function () {
    [$site, $user] = blogSiteWithOwner();
    [$otherSite, $other] = blogSiteWithOwner();
    $post = SitePost::factory()->for($otherSite)->create();

    $this->actingAs($user)
        ->delete(route('sites.posts.destroy', ['current_team' => $user->currentTeam->slug, 'site' => $site->id, 'post' => $post->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('sites.posts.store', ['current_team' => $user->currentTeam->slug, 'site' => $otherSite->id]), postPayload())
        ->assertNotFound();

    expect(SitePost::count())->toBe(1);
});

test('guests cannot add posts', function () {
    [$site, $user] = blogSiteWithOwner();

    $this->post(route('sites.posts.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), postPayload())
        ->assertRedirect(route('login'));
});

test('the editor receives the posts and page options', function () {
    [$site, $user] = blogSiteWithOwner();
    SitePost::factory()->for($site)->create(['title' => 'First post']);

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Edit')
            ->where('posts.0.title', 'First post')
            ->where('pageOptions.0.value', 'about')
            ->where('site.content.pages', ['about', 'services', 'gallery', 'blog']));
});

test('page choices and service details are saved with the site', function () {
    [$site, $user] = blogSiteWithOwner();

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            ...sitePayload($site, $site->content['headline']),
            'pages' => ['services', 'blog'],
            'services' => [['title' => 'Repairs', 'description' => 'Leaks fixed fast.', 'slug' => 'leak-repairs', 'body' => 'All the detail.']],
        ])
        ->assertRedirect();

    $content = $site->fresh()->content;

    expect($content['pages'])->toBe(['services', 'blog'])
        ->and($content['services'][0])->toBe(['title' => 'Repairs', 'description' => 'Leaks fixed fast.', 'slug' => 'leak-repairs', 'body' => 'All the detail.']);
});

test('service slugs and page names are validated', function () {
    [$site, $user] = blogSiteWithOwner();

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            ...sitePayload($site, 'Headline'),
            'pages' => ['pricing'],
            'services' => [['title' => 'Repairs', 'description' => 'Fast.', 'slug' => 'Not A Slug']],
        ])
        ->assertSessionHasErrors(['pages.0', 'services.0.slug']);
});

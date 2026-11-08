<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\SitePost;
use App\Models\User;
use App\Services\SiteBuilder\SiteBlogIntent;
use App\Services\SiteBuilder\SiteEditExtras;
use App\Services\SiteBuilder\SiteEditor;
use App\Services\SiteBuilder\SiteServicesIntent;

/**
 * @return array{0: Site, 1: User}
 */
function intentEditorSite(array $content = []): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'name' => 'Handyman Mike',
        'category' => 'Handyman',
        'city' => 'Austin',
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'pages' => ['about', 'services'],
            'services' => [
                [
                    'title' => 'General Repairs',
                    'description' => str_repeat('Everyday fixes around the house. ', 12),
                    'slug' => 'general-repairs',
                    'body' => str_repeat('Solid repair copy. ', 30),
                ],
                [
                    'title' => 'Furniture Assembly',
                    'description' => str_repeat('Put furniture together carefully. ', 12),
                    'slug' => 'furniture-assembly',
                    'body' => str_repeat('Assembly done carefully. ', 20),
                ],
            ],
            ...$content,
        ],
    ]);

    return [$site, $user];
}

test('chat can generate published blog posts and enable the blog page', function () {
    [$site] = intentEditorSite();
    SiteEditExtras::reset();

    $result = app(SiteBlogIntent::class)->apply($site, $site->content, 'generate 2 blog posts about handyman tips');

    expect($site->posts()->published()->count())->toBe(2)
        ->and($result['content']['pages'])->toContain('blog')
        ->and($result['changed'])->toBeTrue()
        ->and(SiteEditExtras::lines())->not->toBeEmpty();
});

test('chat can remove all blog posts', function () {
    [$site] = intentEditorSite();
    SitePost::factory()->count(3)->for($site)->create();
    SiteEditExtras::reset();

    $result = app(SiteBlogIntent::class)->apply($site, $site->content, 'remove all blogs');

    expect($site->posts()->count())->toBe(0)
        ->and($result['changed'])->toBeTrue();
});

test('chat can remove one blog post by title', function () {
    [$site] = intentEditorSite();
    SitePost::factory()->for($site)->create(['title' => 'Winter pipe tips', 'slug' => 'winter-pipe-tips']);
    SitePost::factory()->for($site)->create(['title' => 'Spring checklist', 'slug' => 'spring-checklist']);
    SiteEditExtras::reset();

    app(SiteBlogIntent::class)->apply($site, $site->content, 'delete the blog post titled "Winter pipe tips"');

    expect($site->posts()->pluck('title')->all())->toBe(['Spring checklist']);
});

test('chat can add named services', function () {
    [$site] = intentEditorSite();
    SiteEditExtras::reset();

    $result = app(SiteServicesIntent::class)->apply(
        $site->lead,
        $site->content,
        'add services "Deck Repair" and "Painting"',
    );

    $titles = collect($result['content']['services'])->pluck('title')->all();

    expect($titles)->toContain('Deck Repair', 'Painting', 'General Repairs')
        ->and($result['changed'])->toBeTrue();
});

test('chat can remove one service by name', function () {
    [$site] = intentEditorSite();
    SiteEditExtras::reset();

    $result = app(SiteServicesIntent::class)->apply(
        $site->lead,
        $site->content,
        'remove the service "Furniture Assembly"',
    );

    expect(collect($result['content']['services'])->pluck('title')->all())
        ->toBe(['General Repairs']);
});

test('the site editor applies blog generation from a chat instruction', function () {
    [$site] = intentEditorSite();
    SiteEditExtras::reset();
    config(['services.anthropic.key' => null]);

    $draft = app(SiteEditor::class)->apply($site, 'generate 3 blog posts for local SEO');

    expect($site->fresh()->posts()->published()->count())->toBe(3)
        ->and($draft->content['pages'])->toContain('blog');
});

<?php

use App\Enums\ReviewsStyle;
use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteBuilder\SiteQualityChecker;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a site whose lead has the given number of visible reviews.
 *
 * @param  array<string, mixed>  $content
 */
function siteWithReviews(int $count, array $content = [], ?User $user = null): Site
{
    $lead = Lead::factory()->create([
        ...($user ? ['team_id' => $user->currentTeam->id] : []),
        'rating' => 4.8,
        'reviews_count' => 120,
    ]);

    LeadReview::factory()->count($count)->for($lead)->sequence(fn ($sequence) => [
        'author_name' => 'Reviewer '.($sequence->index + 1),
        'body' => 'Wonderful experience number '.($sequence->index + 1).', would book again.',
    ])->create();

    $site = Site::factory()->create(['lead_id' => $lead->id]);
    $site->update(['content' => [...$site->content, ...$content]]);

    return $site;
}

test('google reviews appear on the preview with attribution', function () {
    $site = siteWithReviews(4);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertSee('What our customers say')
        ->assertSee('Reviewer 1')
        ->assertSee('Wonderful experience number 2')
        ->assertSee('Google review')
        ->assertSee('Read all reviews on Google')
        ->assertSee('data-reviews-carousel', escape: false);
});

test('hidden reviews are not shown', function () {
    $site = siteWithReviews(3);
    LeadReview::factory()->hidden()->for($site->lead)->create(['author_name' => 'Secret Person']);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertSee('Reviewer 1')
        ->assertDontSee('Secret Person');
});

test('the reviews section is left out when there are too few reviews to show', function () {
    $site = siteWithReviews(Lead::MIN_TESTIMONIALS - 1);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertDontSee('What our customers say');
});

test('the reviews section is left out when the site does not include it', function () {
    $site = siteWithReviews(5, ['sections' => ['services', 'about', 'contact']]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertDontSee('What our customers say');
});

test('review text is escaped', function () {
    $site = siteWithReviews(3);
    $site->lead->reviews()->first()->update(['body' => '<script>alert("x")</script> lovely staff and prices']);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertDontSee('<script>alert("x")</script>', escape: false)
        ->assertSee('lovely staff and prices');
});

test('every reviews style renders its own layout', function (string $style, string $marker, int $reviews) {
    $site = siteWithReviews($reviews, ['reviews_style' => $style]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertSee($marker, escape: false);
})->with([
    'carousel' => ['carousel', 'data-reviews-carousel', 4],
    'grid' => ['grid', 'class="reviews-grid"', 4],
    'spotlight' => ['spotlight', 'data-reviews-spotlight', 4],
    'marquee' => ['marquee', 'class="reviews-marquee"', 6],
]);

test('the ticker falls back to the carousel when there are too few reviews', function () {
    $site = siteWithReviews(3, ['reviews_style' => 'marquee']);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertSee('data-reviews-carousel', escape: false)
        ->assertDontSee('class="reviews-marquee"', escape: false);
});

test('every theme renders the reviews section', function (SiteTemplate $template) {
    $site = siteWithReviews(4);
    $site->update(['template' => $template]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertSee('What our customers say');
})->with(SiteTemplate::cases());

test('the editor receives the reviews, their visibility and the style options', function () {
    $user = User::factory()->create();
    $site = siteWithReviews(3, [], $user);
    LeadReview::factory()->hidden()->for($site->lead)->create();

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Edit')
            ->has('reviews.items', 4)
            ->where('reviews.items.3.isVisible', false)
            ->where('reviews.minimum', Lead::MIN_TESTIMONIALS)
            ->where('reviews.totalOnGoogle', 120)
            ->has('reviewsStyles', count(ReviewsStyle::cases())));
});

test('the chosen reviews style is saved and an unknown one is rejected', function () {
    $user = User::factory()->create();
    $site = siteWithReviews(3, [], $user);
    $payload = [
        'template' => 'modern-clean',
        ...$site->content,
        'sections' => SiteSection::defaultOrder(),
        'reviews_style' => 'spotlight',
    ];
    $url = route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]);

    $this->actingAs($user)->put($url, $payload)->assertRedirect();

    expect($site->fresh()->content['reviews_style'])->toBe('spotlight');

    $this->actingAs($user)->put($url, [...$payload, 'reviews_style' => 'confetti'])
        ->assertSessionHasErrors('reviews_style');
});

test('the quality check reports whether reviews are shown', function () {
    $shown = siteWithReviews(3);
    $few = siteWithReviews(1);
    $none = Site::factory()->create(['lead_id' => Lead::factory()->create(['reviews_count' => 2])->id]);

    $find = fn (Site $site) => collect(app(SiteQualityChecker::class)->check($site)['checks'])->firstWhere('key', 'reviews');

    expect($find($shown)['status'])->toBe('pass')
        ->and($find($few)['status'])->toBe('warn')
        ->and($find($none)['status'])->toBe('pass');
});

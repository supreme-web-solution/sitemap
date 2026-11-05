<?php

use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\Site;
use App\Services\SiteBuilder\RuleBasedCopywriter;
use App\Services\SiteBuilder\SiteServiceCopy;

test('a category label becomes four service pages with solid copy', function () {
    $lead = Lead::factory()->create([
        'name' => 'Handyman Ram',
        'category' => 'Handyman/Handywoman/Handyperson',
        'city' => 'Austin',
        'listing_services' => ['Handyman/Handywoman/Handyperson'],
    ]);

    $shaped = (new SiteServiceCopy)->build(
        $lead,
        app(RuleBasedCopywriter::class)->serviceFillers($lead->category),
        [['title' => 'Handyman/Handywoman/Handyperson', 'description' => 'Professional handyman services delivered with care.']],
    );

    expect($shaped['services'])->toHaveCount(SiteServiceCopy::PAGED)
        ->and($shaped['additional_services'])->toBe([])
        ->and(collect($shaped['services'])->pluck('title')->all())->not->toContain('Handyman/Handywoman/Handyperson');

    foreach ($shaped['services'] as $service) {
        expect(mb_strlen($service['description']))->toBeGreaterThanOrEqual(SiteServiceCopy::MIN_DESCRIPTION)
            ->and(mb_strlen($service['body']))->toBeGreaterThanOrEqual(SiteServiceCopy::MIN_BODY)
            ->and($service['slug'])->not->toBeEmpty();
    }
});

test('extra listing services stay on the services page without their own pages', function () {
    $lead = Lead::factory()->create([
        'name' => 'Handyman Mike',
        'category' => 'Handyman',
        'city' => 'Austin',
        'listing_services' => [
            'General Repairs',
            'Furniture Assembly',
            'Mounting and Installation',
            'Minor Plumbing',
            'Painting and Touch-Ups',
            'Odd Jobs',
        ],
    ]);

    $shaped = (new SiteServiceCopy)->build(
        $lead,
        app(RuleBasedCopywriter::class)->serviceFillers($lead->category),
    );

    expect($shaped['services'])->toHaveCount(4)
        ->and(collect($shaped['services'])->pluck('title')->all())->toBe([
            'General Repairs',
            'Furniture Assembly',
            'Mounting and Installation',
            'Minor Plumbing',
        ])
        ->and($shaped['additional_services'])->toBe([
            'Painting and Touch-Ups',
            'Odd Jobs',
        ]);
});

test('thin or empty services are filled when the site is shown', function () {
    $lead = Lead::factory()->create([
        'name' => 'Handyman Mike',
        'category' => 'Handyman',
        'city' => 'Austin',
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'services' => [
                ['title' => 'Furniture Assembly', 'description' => 'Flat-pack furniture.', 'slug' => 'furniture-assembly'],
            ],
        ],
    ]);

    $html = $this->get(route('sites.preview', [
        'site' => $site->slug,
        'path' => 'services/furniture-assembly',
        'internal' => 1,
    ]))->assertOk()->getContent();

    expect($html)
        ->not->toContain('Want to know more about Furniture Assembly?')
        ->and($html)->toContain('Furniture Assembly')
        ->and(mb_strlen(strip_tags($html)))->toBeGreaterThan(800);
});

test('the service page shows the reviews carousel when reviews are stored', function () {
    $lead = Lead::factory()->create(['name' => 'Handyman Mike', 'category' => 'Handyman', 'city' => 'Austin']);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'services' => [
                ['title' => 'General Repairs', 'description' => 'Everyday fixes around the house.', 'slug' => 'general-repairs', 'body' => str_repeat('Solid repair copy. ', 30)],
            ],
        ],
    ]);

    foreach (['Alex', 'Sam', 'Jordan'] as $author) {
        LeadReview::factory()->create([
            'lead_id' => $lead->id,
            'author_name' => $author,
            'rating' => 5,
            'body' => 'Great work from start to finish on a careful repair job around the house.',
            'is_visible' => true,
        ]);
    }

    $this->get(route('sites.preview', [
        'site' => $site->slug,
        'path' => 'services/general-repairs',
        'internal' => 1,
    ]))
        ->assertOk()
        ->assertSee('What our customers say')
        ->assertSee('data-reviews-carousel', false);
});

test('the contact page puts the form left of the details with the map below', function () {
    $lead = Lead::factory()->create([
        'name' => 'Handyman Mike',
        'phone' => '+15125550123',
        'email' => 'mike@example.com',
        'address' => '5211 Eilers Ave, Austin, TX',
        'opening_hours' => ['Monday' => '8 AM to 6 PM'],
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'contact', 'internal' => 1]))
        ->assertOk()
        ->assertSee('data-contact-layout="form-details"', false)
        ->assertSeeInOrder(['contact-form', 'Contact details', 'Opening Hours'], false)
        ->assertSee('name="name"', false)
        ->assertSee('name="contact"', false)
        ->assertSee('name="message"', false)
        ->assertSee('+15125550123')
        ->assertSee('5211 Eilers Ave, Austin, TX');
});

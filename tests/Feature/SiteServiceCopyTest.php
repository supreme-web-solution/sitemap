<?php

use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\Site;
use App\Models\User;
use App\Notifications\SiteContactNotification;
use App\Services\SiteBuilder\RuleBasedCopywriter;
use App\Services\SiteBuilder\SiteServiceCopy;
use Illuminate\Support\Facades\Notification;

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

test('the service page includes other services and a full-width map when available', function () {
    $lead = Lead::factory()->create([
        'name' => 'Handyman Mike',
        'category' => 'Handyman',
        'city' => 'Austin',
        'address' => '5211 Eilers Ave, Austin, TX 78751',
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'services' => [
                [
                    'title' => 'General Repairs',
                    'description' => 'Everyday fixes around the house.',
                    'slug' => 'general-repairs',
                    'body' => str_repeat('Solid repair copy. ', 30),
                ],
                [
                    'title' => 'Furniture Assembly',
                    'description' => 'Put together tables, chairs, and shelves.',
                    'slug' => 'furniture-assembly',
                    'body' => str_repeat('Assembly done carefully. ', 20),
                ],
                [
                    'title' => 'Minor Plumbing',
                    'description' => 'Small plumbing jobs done right.',
                    'slug' => 'minor-plumbing',
                    'body' => str_repeat('Plumbing help nearby. ', 20),
                ],
            ],
        ],
    ]);

    $this->get(route('sites.preview', [
        'site' => $site->slug,
        'path' => 'services/general-repairs',
        'internal' => 1,
    ]))
        ->assertOk()
        ->assertSee('Other services')
        ->assertSee('Furniture Assembly')
        ->assertSee('Minor Plumbing')
        ->assertSee('/services/furniture-assembly', false)
        ->assertSee('service-map', false)
        ->assertSee('maps.google.com/maps', false)
        ->assertSee('Find us in Austin');
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
        ->assertSee('placeholder="Jane Smith"', false)
        ->assertSee('placeholder="(555) 123-4567 or you@email.com"', false)
        ->assertSee('placeholder="What do you need help with?"', false)
        ->assertSee('+15125550123')
        ->assertSee('5211 Eilers Ave, Austin, TX');
});

test('the contact form can sit on the right with a modern card layout', function () {
    $site = Site::factory()->create();
    $content = $site->content;
    $content['variants']['contact'] = 'form-right';
    $site->update(['content' => $content]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'path' => 'contact', 'internal' => 1]))
        ->assertOk()
        ->assertSee('contact-v-form-right', false)
        ->assertSee('data-contact-layout="details-form"', false);

    $content['variants']['contact'] = 'card';
    $site->update(['content' => $content]);

    $this->get(route('sites.preview', ['site' => $site->fresh()->slug, 'path' => 'contact', 'internal' => 1]))
        ->assertOk()
        ->assertSee('contact-v-card', false)
        ->assertSee('data-contact-layout="stacked"', false);
});

test('the contact form requires a name phone or email and message', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $lead = Lead::factory()->for($team)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id, 'team_id' => $team->id]);

    $this->from(route('sites.preview', ['site' => $site->slug, 'path' => 'contact', 'internal' => 1]))
        ->post(route('sites.preview.contact', $site), [
            'name' => '',
            'contact' => '',
            'message' => '',
            'internal' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['name', 'contact', 'message']);

    $this->from(route('sites.preview', ['site' => $site->slug, 'path' => 'contact', 'internal' => 1]))
        ->post(route('sites.preview.contact', $site), [
            'name' => 'Jane Smith',
            'contact' => 'jane@example.com',
            'message' => 'Need a deep clean next week.',
            'internal' => 1,
        ])
        ->assertRedirect();

    expect($lead->activities()->count())->toBe(1);

    Notification::assertSentTo($owner, SiteContactNotification::class);
});

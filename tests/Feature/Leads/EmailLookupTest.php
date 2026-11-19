<?php

use App\Enums\LeadSearchStatus;
use App\Enums\SiteGenerationStatus;
use App\Jobs\LookupLeadEmail;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

test('extract lookup saves the email from the maps listing', function () {
    config(['services.mindcase.key' => 'mk_test_key']);
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'status' => 'completed',
            'data' => [mindcasePlace(['emails' => ['owner@maps.test']])],
        ]),
    ]);

    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'team_id' => $user->currentTeam->id,
        'email' => null,
        'maps_url' => 'https://maps.google.com/?cid=1',
        'website' => 'https://example.test',
    ]);

    $this->actingAs($user)
        ->post(route('leads.email-lookup', ['current_team' => $user->currentTeam->slug, 'lead' => $lead]))
        ->assertRedirect();

    expect($lead->fresh())
        ->email->toBe('owner@maps.test')
        ->email_lookup_status->toBe(SiteGenerationStatus::Idle);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'leads/contacts'));
});

test('a finished lookup with no email is remembered so the button can point to maps', function () {
    config(['services.mindcase.key' => 'mk_test_key']);
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'status' => 'completed',
            'data' => [mindcasePlace(['emails' => [], 'website' => null])],
        ]),
    ]);

    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'team_id' => $user->currentTeam->id,
        'email' => null,
        'website' => null,
        'maps_url' => 'https://www.google.com/maps/place/Top+Choice+Remodeling/@29.81,-95.43,17z',
    ]);

    $this->actingAs($user)
        ->post(route('leads.email-lookup', ['current_team' => $user->currentTeam->slug, 'lead' => $lead]))
        ->assertRedirect();

    expect($lead->fresh())
        ->email->toBeNull()
        ->email_lookup_empty->toBeTrue()
        ->email_lookup_status->toBe(SiteGenerationStatus::Idle);
});

test('extract lookup stays queued when the job has not finished', function () {
    Bus::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'team_id' => $user->currentTeam->id,
        'email' => null,
        'maps_url' => 'https://maps.google.com/?cid=9',
    ]);

    $this->actingAs($user)
        ->post(route('leads.email-lookup', ['current_team' => $user->currentTeam->slug, 'lead' => $lead]))
        ->assertRedirect();

    expect($lead->fresh()->email_lookup_status)->toBe(SiteGenerationStatus::Queued);
    Bus::assertDispatched(LookupLeadEmail::class);
});

test('extract lookup reads the business website when maps has no email', function () {
    config(['services.mindcase.key' => 'mk_test_key']);
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'status' => 'completed',
            'data' => [mindcasePlace(['emails' => []])],
        ]),
        'api.mindcase.co/v1/data/leads/contacts/run*' => Http::response([
            'status' => 'completed',
            'data' => [[
                'domain' => 'crescent.test',
                'email' => 'hello@crescent.test',
            ]],
        ]),
    ]);

    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'team_id' => $user->currentTeam->id,
        'email' => null,
        'website' => 'https://www.crescent.test',
        'maps_url' => 'https://maps.google.com/?cid=2',
    ]);

    $this->actingAs($user)
        ->post(route('leads.email-lookup', ['current_team' => $user->currentTeam->slug, 'lead' => $lead]))
        ->assertRedirect();

    expect($lead->fresh()->email)->toBe('hello@crescent.test');
});

test('an email search looks up the website when maps did not include an email', function () {
    config(['services.mindcase.key' => 'mk_test_key']);
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'job_id' => 'job-web',
            'status' => 'completed',
            'data' => [mindcasePlace([
                'placeId' => 'ChIJ-site-only',
                'businessName' => 'Site Only',
                'emails' => [],
                'website' => 'https://www.siteonly.test',
            ])],
        ]),
        'api.mindcase.co/v1/jobs/job-web/results' => Http::response([
            'job_id' => 'job-web',
            'status' => 'completed',
            'data' => [mindcasePlace([
                'placeId' => 'ChIJ-site-only',
                'businessName' => 'Site Only',
                'emails' => [],
                'website' => 'https://www.siteonly.test',
            ])],
        ]),
        'api.mindcase.co/v1/data/leads/contacts/run*' => Http::response([
            'status' => 'completed',
            'data' => [[
                'domain' => 'siteonly.test',
                'email' => 'hello@siteonly.test',
            ]],
        ]),
    ]);

    $search = LeadSearch::factory()->create([
        'keyword' => 'Kitchen & Bath Remodeling',
        'location' => 'Houston, Texas',
        'max_results' => 20,
        'require_email' => true,
    ]);

    runLeadSearch($search)->assertReleased(1);

    expect($search->fresh()->status)->toBe(LeadSearchStatus::Running);

    runLeadSearch($search->fresh())->assertNotReleased();

    expect($search->team->leads()->sole()->email)->toBe('hello@siteonly.test');
});

test('an email search looks up only as many businesses as the result count', function () {
    config(['services.mindcase.key' => 'mk_test_key']);
    Http::preventStrayRequests();

    $places = [
        mindcasePlace(['placeId' => 'ChIJ-1', 'businessName' => 'First', 'emails' => [], 'website' => 'https://www.first.test']),
        mindcasePlace(['placeId' => 'ChIJ-2', 'businessName' => 'Second', 'emails' => [], 'website' => 'https://www.second.test']),
        mindcasePlace(['placeId' => 'ChIJ-3', 'businessName' => 'Third', 'emails' => [], 'website' => 'https://www.third.test']),
    ];

    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'job_id' => 'job-cap',
            'status' => 'completed',
            'data' => $places,
        ]),
        'api.mindcase.co/v1/jobs/job-cap/results' => Http::response([
            'job_id' => 'job-cap',
            'status' => 'completed',
            'data' => $places,
        ]),
        'api.mindcase.co/v1/data/leads/contacts/run*' => Http::response([
            'status' => 'completed',
            'data' => [[
                'domain' => 'first.test',
                'email' => 'hello@first.test',
            ]],
        ]),
    ]);

    $search = LeadSearch::factory()->create([
        'max_results' => 1,
        'require_email' => true,
    ]);

    runLeadSearch($search)->assertReleased(1);
    runLeadSearch($search->fresh())->assertNotReleased();

    expect($search->team->leads()->pluck('email')->all())->toBe(['hello@first.test']);

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), 'leads/contacts')) {
            return false;
        }

        return $request['params']['websites'] === ['https://www.first.test'];
    });
});

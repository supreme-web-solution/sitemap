<?php

use App\Enums\LeadSearchStatus;
use App\Jobs\RunLeadSearch;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\User;
use Illuminate\Http\Client\Request;
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

    expect($lead->fresh()->email)->toBe('owner@maps.test');

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'leads/contacts'));
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

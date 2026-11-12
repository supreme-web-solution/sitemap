<?php

use App\Actions\Leads\StoreLeadsFromListings;
use App\Enums\UsageMetric;
use App\Exceptions\PlanLimitException;
use App\Jobs\RunLeadSearch;
use App\Models\Lead;
use App\Models\LeadSearch;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\PlanLimitReachedNotification;
use App\Services\Ai\AiCreditLog;
use App\Services\Billing\PlanLimitNotifier;
use App\Services\Billing\UsageMeter;
use App\Services\LeadFinder\LeadFinder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

test('hitting a hard plan cap emails the team owner once per day', function () {
    Notification::fake();

    $user = User::factory()->create();
    $plan = Plan::factory()->create(['limits' => ['lead_searches' => 1]]);
    $team = $user->currentTeam;
    $team->forceFill(['plan_id' => $plan->id])->save();

    $usage = app(UsageMeter::class);
    $usage->consume($team, UsageMetric::LeadSearches);

    try {
        $usage->consume($team, UsageMetric::LeadSearches);
    } catch (PlanLimitException) {
        //
    }

    try {
        $usage->consume($team, UsageMetric::LeadSearches);
    } catch (PlanLimitException) {
        //
    }

    Notification::assertSentToTimes($user, PlanLimitReachedNotification::class, 1);
});

test('a lead search blocked by the saved-leads cap emails the owner', function () {
    Notification::fake();
    Http::preventStrayRequests();
    Http::fake([
        'api.mindcase.co/v1/data/google-maps/places/run*' => Http::response([
            'job_id' => 'job-limit-mail',
            'status' => 'completed',
            'data' => [[
                'placeId' => 'ChIJ-delhi-1',
                'businessName' => 'Delhi Locksmith',
                'category' => 'Locksmith',
                'phone' => '+91110000000',
                'emails' => [],
                'website' => null,
                'address' => 'Delhi',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'country' => 'IN',
                'latitude' => 28.6,
                'longitude' => 77.2,
                'rating' => 4.5,
                'reviews' => 10,
                'placeUrl' => 'https://maps.google.com/?cid=1',
                'permanentlyClosed' => false,
            ]],
        ]),
    ]);

    $user = User::factory()->create();
    $plan = Plan::factory()->create(['limits' => ['leads' => 1]]);
    $team = $user->currentTeam;
    $team->forceFill(['plan_id' => $plan->id])->save();
    Lead::factory()->for($team)->create(['place_id' => 'already-there']);

    $search = LeadSearch::factory()->for($team)->create([
        'user_id' => $user->id,
        'keyword' => 'locksmith',
        'location' => 'Delhi, India',
    ]);

    (new RunLeadSearch($search))->handle(
        app(LeadFinder::class),
        app(StoreLeadsFromListings::class),
        app(AiCreditLog::class),
    );

    Notification::assertSentTo($user, function (PlanLimitReachedNotification $notification) use ($team) {
        expect($notification->team->is($team))->toBeTrue()
            ->and($notification->metric)->toBe(UsageMetric::Leads)
            ->and($notification->blockedCount)->toBe(1)
            ->and($notification->context)->toContain('locksmith');

        $mail = $notification->toMail($notification->team->owner());

        expect($mail->subject)->toContain('Plan limit reached')
            ->and($mail->render())->toContain('View plan');

        return true;
    });
});

test('plan limit emails stay quiet inside the throttle window', function () {
    Notification::fake();

    $user = User::factory()->create();
    $plan = Plan::factory()->create(['limits' => ['leads' => 1]]);
    $team = $user->currentTeam;
    $team->forceFill(['plan_id' => $plan->id])->save();

    $notifier = app(PlanLimitNotifier::class);
    $notifier->notify($team, UsageMetric::Leads, 1, 5, 'First hit');
    $notifier->notify($team, UsageMetric::Leads, 1, 8, 'Second hit');

    Notification::assertSentToTimes($user, PlanLimitReachedNotification::class, 1);
});

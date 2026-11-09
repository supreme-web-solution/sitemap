<?php

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\TeamRole;
use App\Models\Lead;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Notifications\PreviewInterestNotification;
use Illuminate\Support\Facades\Notification;

test('a prospect can claim a website from the preview', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $lead = Lead::factory()->for($team)->create(['status' => LeadStatus::SiteGenerated]);
    $site = Site::factory()->create(['lead_id' => $lead->id, 'team_id' => $team->id]);

    $this->post(route('sites.preview.interest', $site->slug), [
        'intent' => 'claim',
        'name' => 'Jordan Owner',
        'contact' => 'jordan@example.com',
        'message' => 'We want this live on our domain.',
    ])->assertRedirect();

    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Interested)
        ->and($lead->activities()->where('type', LeadActivityType::PreviewClaim)->first()->data)
        ->toMatchArray([
            'contact' => 'jordan@example.com',
            'intent' => 'claim',
            'message' => 'We want this live on our domain.',
        ]);

    Notification::assertSentTo($owner, PreviewInterestNotification::class, function (PreviewInterestNotification $notification) {
        return $notification->intent === 'claim';
    });
});

test('the preview shows a claim form instead of a bare mailto', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $lead = Lead::factory()->for($team)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id, 'team_id' => $team->id]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSee('Claim this website')
        ->assertSee('name="intent" value="claim"', false)
        ->assertDontSee('mailto:');
});

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

/**
 * @return array{0: Site, 1: User}
 */
function siteWithOwner(array $leadAttributes = []): array
{
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $lead = Lead::factory()->for($team)->create($leadAttributes);

    return [Site::factory()->create(['lead_id' => $lead->id]), $owner];
}

test('a business owner can reply from the preview and the lead moves to interested', function () {
    Notification::fake();

    [$site, $owner] = siteWithOwner(['status' => LeadStatus::SiteGenerated]);

    $this->post(route('sites.preview.interest', $site->slug), [
        'name' => 'Sam Owner',
        'contact' => 'sam@example.com',
        'message' => 'Can you add a booking form?',
    ])->assertRedirect(route('sites.preview', ['site' => $site->slug, 'thanks' => 1, 'internal' => 1]));

    $lead = $site->lead->fresh();

    expect($lead->status)->toBe(LeadStatus::Interested)
        ->and($lead->next_follow_up_at->isToday())->toBeTrue()
        ->and($lead->activities()->where('type', LeadActivityType::PreviewInterest)->first()->data)
        ->toMatchArray(['contact' => 'sam@example.com', 'message' => 'Can you add a booking form?']);

    Notification::assertSentTo($owner, PreviewInterestNotification::class);
});

test('replying never moves a won lead backwards', function () {
    Notification::fake();

    [$site] = siteWithOwner(['status' => LeadStatus::Won]);

    $this->post(route('sites.preview.interest', $site->slug), ['contact' => 'sam@example.com'])->assertRedirect();

    expect($site->lead->fresh()->status)->toBe(LeadStatus::Won);
});

test('a contact method is required', function () {
    [$site] = siteWithOwner();

    $this->post(route('sites.preview.interest', $site->slug), ['name' => 'Sam'])
        ->assertSessionHasErrors('contact');
});

test('submissions that fill the honeypot are ignored', function () {
    Notification::fake();

    [$site] = siteWithOwner();

    $this->post(route('sites.preview.interest', $site->slug), [
        'contact' => 'bot@example.com',
        'company_url' => 'https://spam.example',
    ])->assertRedirect();

    expect($site->lead->activities()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('the preview offers the discuss form and shows a thank you after replying', function () {
    [$site] = siteWithOwner();

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSee('Discuss this website')
        ->assertSee(route('sites.preview.interest', $site->slug), escape: false);

    $this->get(route('sites.preview', ['site' => $site->slug, 'thanks' => 1, 'internal' => 1]))
        ->assertSee('Thank you!');

    expect($site->fresh()->views_count)->toBe(1);
});

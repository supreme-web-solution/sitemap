<?php

use App\Enums\LeadActivityType;
use App\Enums\ProposalStatus;
use App\Enums\TeamRole;
use App\Models\Lead;
use App\Models\Package;
use App\Models\PreviewComment;
use App\Models\Proposal;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Notifications\PreviewCommentNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('an expired preview is gone', function () {
    $site = Site::factory()->create(['preview_expires_at' => now()->subDay()]);

    $this->get(route('sites.preview', $site->slug))->assertStatus(410);
});

test('a password protected preview unlocks for the session', function () {
    $site = Site::factory()->create(['preview_password' => 'secret12']);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSee('This preview is private');

    $this->post(route('sites.preview.unlock', $site->slug), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    $this->post(route('sites.preview.unlock', $site->slug), ['password' => 'secret12'])
        ->assertRedirect(route('sites.preview', $site->slug));

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSee($site->content['headline']);

    expect(Hash::check('secret12', $site->fresh()->preview_password))->toBeTrue();
});

test('team members skip the password and expiry with the internal flag', function () {
    $member = User::factory()->create();
    $lead = Lead::factory()->for($member->currentTeam)->create();
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'preview_password' => 'secret12',
        'preview_expires_at' => now()->subDay(),
    ]);

    $this->actingAs($member)
        ->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertSee($site->content['headline']);
});

test('the internal flag does not unlock a preview for visitors', function () {
    $protected = Site::factory()->create(['preview_password' => 'secret12']);
    $expired = Site::factory()->create(['preview_expires_at' => now()->subDay()]);

    $this->get(route('sites.preview', ['site' => $protected->slug, 'internal' => 1]))
        ->assertSee('This preview is private')
        ->assertDontSee($protected->content['headline']);

    $this->get(route('sites.preview', ['site' => $expired->slug, 'internal' => 1]))->assertStatus(410);
});

test('a comment on the preview is stored on the lead timeline', function () {
    $site = Site::factory()->create();

    $this->post(route('sites.preview.comments', $site->slug), [
        'author_name' => 'Sam',
        'author_contact' => 'sam@example.com',
        'section' => 'about',
        'body' => 'Please use our real photos.',
    ])->assertRedirect();

    expect(PreviewComment::sole())
        ->author_name->toBe('Sam')
        ->section->toBe('about');

    expect($site->lead->activities()->where('type', LeadActivityType::ClientFeedback)->exists())->toBeTrue();
});

test('a preview comment emails the agency owner and is stored on the lead', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $lead = Lead::factory()->for($team)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id, 'team_id' => $team->id]);

    $this->post(route('sites.preview.comments', $site->slug), [
        'author_name' => 'Aurelia Harrison',
        'author_contact' => 'aurelia@example.com',
        'section' => 'Hero',
        'body' => 'Please use a photo of our real van.',
    ])->assertRedirect();

    $activity = $lead->activities()->where('type', LeadActivityType::ClientFeedback)->sole();

    expect($activity->data)->toMatchArray([
        'name' => 'Aurelia Harrison',
        'contact' => 'aurelia@example.com',
        'section' => 'Hero',
        'body' => 'Please use a photo of our real van.',
    ]);

    Notification::assertSentTo($owner, PreviewCommentNotification::class);
});

test('a sent proposal shows a see pricing button on the preview', function () {
    $site = Site::factory()->create();
    $proposal = Proposal::factory()->withDefaultItems()->create([
        'lead_id' => $site->lead_id,
        'team_id' => $site->team_id,
        'status' => ProposalStatus::Sent,
        'sent_at' => now(),
    ]);
    Package::factory()->for($site->team)->create(['name' => 'Hidden package', 'is_active' => true]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertSee('See pricing')
        ->assertSee($proposal->publicUrl(), escape: false)
        ->assertDontSee('Hidden package')
        ->assertDontSee('$500.00');
});

test('packages never appear on the preview bar', function () {
    $site = Site::factory()->create();
    Package::factory()->for($site->team)->create(['name' => 'Starter site', 'unit_cents' => 50000, 'is_active' => true]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertDontSee('See pricing')
        ->assertDontSee('Starter site')
        ->assertDontSee('$500.00');
});

test('a draft proposal does not show pricing until it is published', function () {
    $site = Site::factory()->create();
    Proposal::factory()->withDefaultItems()->create([
        'lead_id' => $site->lead_id,
        'team_id' => $site->team_id,
        'status' => ProposalStatus::Draft,
        'sent_at' => null,
    ]);
    Package::factory()->for($site->team)->create(['name' => 'Starter site', 'is_active' => true]);

    $this->get(route('sites.preview', $site->slug))
        ->assertOk()
        ->assertDontSee('See pricing')
        ->assertDontSee('Starter site');
});

test('the agency can set preview expiry and a password', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->put(route('sites.preview-access.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'preview_expires_at' => now()->addWeek()->toDateString(),
            'preview_password' => 'gatekeep',
        ])
        ->assertRedirect();

    expect($site->fresh()->preview_expires_at->toDateString())->toBe(now()->addWeek()->toDateString())
        ->and(Hash::check('gatekeep', $site->fresh()->preview_password))->toBeTrue();
});

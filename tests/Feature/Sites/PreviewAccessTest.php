<?php

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\ProposalStatus;
use App\Models\Lead;
use App\Models\Package;
use App\Models\PreviewComment;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

test('internal editor previews skip the password and expiry', function () {
    $site = Site::factory()->create([
        'preview_password' => 'secret12',
        'preview_expires_at' => now()->subDay(),
    ]);

    $this->get(route('sites.preview', ['site' => $site->slug, 'internal' => 1]))
        ->assertOk()
        ->assertSee($site->content['headline']);
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

test('choosing a package from the preview creates a sent proposal', function () {
    $site = Site::factory()->create();
    $package = Package::factory()->for($site->team)->create(['name' => 'Starter site', 'is_active' => true]);

    $this->post(route('sites.preview.packages', $site->slug), [
        'package_id' => $package->id,
    ])->assertRedirect();

    $proposal = $site->team->proposals()->sole();

    expect($proposal->status)->toBe(ProposalStatus::Sent)
        ->and($proposal->items->sole()->description)->toBe('Starter site')
        ->and($site->lead->fresh()->status)->toBe(LeadStatus::Interested);
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

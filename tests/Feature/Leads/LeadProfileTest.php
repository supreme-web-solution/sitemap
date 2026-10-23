<?php

use App\Enums\SiteGenerationStatus;
use App\Enums\SiteVibe;
use App\Jobs\GenerateLeadSite;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Valid owner review form data.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function leadProfileData(array $overrides = []): array
{
    return [
        'name' => 'Austin Pro Plumbing & Drains',
        'category' => 'Plumber',
        'description' => 'Family plumbers.',
        'phone' => '+1 512 555 0192',
        'email' => 'hello@austinpro.test',
        'address' => '100 Congress Ave, Austin, TX',
        'city' => 'Austin',
        'opening_hours' => ['Monday' => '8 AM to 6 PM', 'Tuesday' => ''],
        'vibe' => 'warm',
        ...$overrides,
    ];
}

test('the review screen shows the business details', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'Austin Pro Plumbing']);

    $this->actingAs($user)
        ->get(route('leads.edit', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/Edit')
            ->where('lead.name', 'Austin Pro Plumbing')
            ->where('lead.detailsConfirmedAt', null)
            ->has('vibes', count(SiteVibe::cases())));
});

test('the owner can confirm and correct the business details', function () {
    Queue::fake();
    Storage::fake('public');

    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);

    $this->actingAs($user)
        ->put(route('leads.profile.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), leadProfileData([
            'logo' => UploadedFile::fake()->image('logo.png'),
            'photos' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
        ]))
        ->assertRedirect(route('leads.edit', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]));

    $lead->refresh();

    expect($lead)
        ->name->toBe('Austin Pro Plumbing & Drains')
        ->vibe->toBe(SiteVibe::Warm)
        ->opening_hours->toBe(['Monday' => '8 AM to 6 PM'])
        ->details_confirmed_at->not->toBeNull()
        ->photo_paths->toHaveCount(2);

    Storage::disk('public')->assertExists([$lead->logo_path, ...$lead->photo_paths]);
    expect($lead->logo_path)->toStartWith("teams/{$user->currentTeam->id}/leads/{$lead->id}/");
    Queue::assertNothingPushed();
});

test('new photos replace the old ones', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $oldPhoto = UploadedFile::fake()->image('old.jpg')->store('teams/1/leads/1', 'public');
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id, 'photo_paths' => [$oldPhoto]]);

    $this->actingAs($user)
        ->put(route('leads.profile.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), leadProfileData([
            'photos' => [UploadedFile::fake()->image('new.jpg')],
        ]));

    Storage::disk('public')->assertMissing($oldPhoto);
    expect($lead->fresh()->photo_paths)->toHaveCount(1)->not->toContain($oldPhoto);
});

test('confirming the details can build the website straight away', function () {
    Queue::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);

    $this->actingAs($user)
        ->put(route('leads.profile.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), leadProfileData(['generate' => true]));

    expect($lead->fresh()->site_generation_status)->toBe(SiteGenerationStatus::Queued);
    Queue::assertPushed(GenerateLeadSite::class, fn (GenerateLeadSite $job) => $job->lead->is($lead));
});

test('uploads must be images', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);

    $this->actingAs($user)
        ->put(route('leads.profile.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), leadProfileData([
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('logo');
});

test('another team lead cannot be reviewed', function () {
    $user = User::factory()->create();
    $otherLead = Lead::factory()->create();

    $this->actingAs($user)
        ->get(route('leads.edit', ['current_team' => $user->currentTeam->slug, 'lead' => $otherLead->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->put(route('leads.profile.update', ['current_team' => $user->currentTeam->slug, 'lead' => $otherLead->id]), leadProfileData())
        ->assertNotFound();
});

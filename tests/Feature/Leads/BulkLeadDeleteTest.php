<?php

use App\Models\Lead;
use App\Models\Proposal;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('selected leads can be deleted in bulk', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $keep = Lead::factory()->create(['team_id' => $team->id, 'name' => 'Keep Me']);
    $first = Lead::factory()->create([
        'team_id' => $team->id,
        'name' => 'Delete One',
        'logo_path' => 'teams/1/leads/bulk-1/logo.png',
    ]);
    $second = Lead::factory()->create(['team_id' => $team->id, 'name' => 'Delete Two']);
    Storage::disk('public')->put('teams/1/leads/bulk-1/logo.png', 'logo');
    $site = Site::factory()->create(['lead_id' => $first->id, 'team_id' => $team->id]);
    $proposal = Proposal::factory()->create(['lead_id' => $second->id, 'team_id' => $team->id]);

    $this->actingAs($user)
        ->from(route('leads.index', ['current_team' => $team->slug]))
        ->delete(route('leads.bulk.destroy', ['current_team' => $team->slug]), [
            'lead_ids' => [$first->id, $second->id],
        ])
        ->assertRedirect();

    expect(Lead::query()->find($first->id))->toBeNull()
        ->and(Lead::query()->find($second->id))->toBeNull()
        ->and(Lead::query()->find($keep->id))->not->toBeNull()
        ->and(Site::query()->find($site->id))->toBeNull()
        ->and(Proposal::query()->find($proposal->id))->toBeNull()
        ->and(Storage::disk('public')->exists('teams/1/leads/bulk-1/logo.png'))->toBeFalse();
});

test('bulk delete only removes leads that belong to the team', function () {
    $user = User::factory()->create();
    $own = Lead::factory()->create(['team_id' => $user->currentTeam->id]);
    $foreign = Lead::factory()->create();

    $this->actingAs($user)
        ->delete(route('leads.bulk.destroy', ['current_team' => $user->currentTeam->slug]), [
            'lead_ids' => [$own->id, $foreign->id],
        ])
        ->assertSessionHasErrors('lead_ids.1');

    expect($own->fresh())->not->toBeNull()
        ->and($foreign->fresh())->not->toBeNull();
});

test('bulk delete requires at least one lead id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('leads.bulk.destroy', ['current_team' => $user->currentTeam->slug]), [
            'lead_ids' => [],
        ])
        ->assertSessionHasErrors('lead_ids');
});

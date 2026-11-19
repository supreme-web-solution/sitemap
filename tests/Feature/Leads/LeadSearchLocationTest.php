<?php

use App\Jobs\RunLeadSearch;
use App\Models\User;
use App\Services\CommandCenter\CommandCenterActionExecutor;
use App\Support\LeadSearchLocation;
use Illuminate\Support\Facades\Bus;

test('a street address keeps the city named in it', function () {
    $resolved = LeadSearchLocation::forSearch('4501 Socastee Blvd #104, Myrtle Beach, SC 29588');

    expect($resolved['ok'])->toBeTrue()
        ->and($resolved['location'])->toBe('Myrtle Beach, South Carolina')
        ->and($resolved['notice'])->toContain('Myrtle Beach, South Carolina')
        ->and($resolved['notice'])->toContain('Dallas, Texas');
});

test('a city is left unchanged', function () {
    $resolved = LeadSearchLocation::forSearch('Dallas, Texas');

    expect($resolved)->toBe([
        'ok' => true,
        'location' => 'Dallas, Texas',
        'notice' => null,
    ]);
});

test('a street with no city is refused and suggests cities', function () {
    $resolved = LeadSearchLocation::forSearch('4501 Socastee Blvd #104');

    expect($resolved['ok'])->toBeFalse()
        ->and($resolved['notice'])->toContain('Dallas, Texas')
        ->and($resolved['notice'])->toContain('Austin, Texas');
});

test('the command center searches the city inside a street address', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $message = app(CommandCenterActionExecutor::class)->run('start_lead_search', [
        'keyword' => "Danny Lee's Place",
        'location' => '4501 Socastee Blvd #104, Myrtle Beach, SC 29588',
    ], $team, $user);

    $search = $team->leadSearches()->first();

    expect($search)->not->toBeNull()
        ->and($search->location)->toBe('Myrtle Beach, South Carolina')
        ->and($search->keyword)->toBe("Danny Lee's Place")
        ->and($message)->toContain('Myrtle Beach, South Carolina')
        ->and($message)->toContain('Dallas, Texas');

    Bus::assertDispatched(RunLeadSearch::class);
});

test('the command center does not search a street that has no city', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $message = app(CommandCenterActionExecutor::class)->run('start_lead_search', [
        'keyword' => 'restaurants',
        'location' => '4501 Socastee Blvd #104',
    ], $team, $user);

    expect($team->leadSearches()->count())->toBe(0)
        ->and($message)->toContain('Dallas, Texas');

    Bus::assertNothingDispatched();
});

<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Services\Media\SiteImageStorage;
use App\Services\SiteBuilder\SiteMediaUndo;

test('a later image edit keeps cloudinary photos the site is still showing', function () {
    $deleted = [];
    $this->instance(SiteImageStorage::class, cloudinaryImageDouble($deleted));

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'logo_path' => 'cloudinary:logo-live',
        'photo_paths' => ['cloudinary:hero-live', 'cloudinary:gallery-live'],
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            ...Site::factory()->make()->content,
            'services' => [
                ['title' => 'Mowing', 'description' => 'Lawns.', 'image_path' => 'cloudinary:service-live'],
            ],
            'images' => ['home_hero' => 'cloudinary:placement-live'],
        ],
    ]);

    $undo = app(SiteMediaUndo::class);
    $undo->remember($site, $lead);

    $lead->photo_paths = ['cloudinary:hero-new', 'cloudinary:gallery-live'];
    $lead->save();

    $undo->remember($site->fresh(), $lead->fresh());

    expect($deleted)->toBe(['cloudinary:hero-live']);
});

test('undo does not delete a cloudinary photo that the restored site still uses', function () {
    $deleted = [];
    $this->instance(SiteImageStorage::class, cloudinaryImageDouble($deleted));

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'photo_paths' => ['cloudinary:hero-old', 'cloudinary:gallery-kept'],
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    app(SiteMediaUndo::class)->remember($site, $lead);

    $lead->photo_paths = ['cloudinary:hero-new', 'cloudinary:gallery-kept'];
    $lead->save();

    $this->actingAs($user)
        ->post(route('sites.media.undo', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertOk();

    expect($deleted)->toBe(['cloudinary:hero-new'])
        ->and($lead->fresh()->photo_paths)->toBe(['cloudinary:hero-old', 'cloudinary:gallery-kept']);
});

/**
 * @param  list<string|null>  $deleted
 */
function cloudinaryImageDouble(array &$deleted): SiteImageStorage
{
    $images = Mockery::mock(SiteImageStorage::class)->makePartial();
    $images->shouldReceive('delete')->andReturnUsing(function (?string $path) use (&$deleted): void {
        $deleted[] = $path;
    });

    return $images;
}

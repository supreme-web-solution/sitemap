<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteBuilder\SiteImagePromptBuilder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('gallery prompts change with the photo and the variation', function () {
    $lead = Lead::factory()->create(['category' => 'Plumbing', 'city' => 'Austin']);

    $first = SiteImagePromptBuilder::forGallery($lead, 1, null, 0);
    $nextSlot = SiteImagePromptBuilder::forGallery($lead, 2, null, 0);
    $sameSlotAgain = SiteImagePromptBuilder::forGallery($lead, 1, null, 1);

    expect($first)->toContain('Gallery photograph')
        ->and($first)->toContain('different scene')
        ->and($first)->not->toBe($nextSlot)
        ->and($first)->not->toBe($sameSlotAgain);
});

test('adding a gallery photo generates from the gallery context without a typed prompt', function () {
    Storage::fake('public');

    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'test-key',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'openrouter.ai/api/v1/images')) {
            return Http::response([
                'data' => [['b64_json' => base64_encode('gallery-image'), 'media_type' => 'image/png']],
            ]);
        }

        return Http::response([], 404);
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'category' => 'Plumbing',
        'city' => 'Austin',
        'photo_paths' => ['teams/1/leads/1/hero.jpg'],
    ]);
    Storage::disk('public')->put('teams/1/leads/1/hero.jpg', 'hero');
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->postJson(route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'generate' => true,
            'slot' => 'photo',
            'index' => 1,
        ])
        ->assertOk()
        ->assertJsonCount(2, 'photos');

    expect($lead->fresh()->photo_paths)->toHaveCount(2)
        ->and($lead->fresh()->photo_paths[0])->toBe('teams/1/leads/1/hero.jpg');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'openrouter.ai/api/v1/images')
        && str_contains($request->body(), 'Gallery photograph')
        && str_contains($request->body(), 'different scene'));
});

test('adding a gallery photo keeps the listing photos already on screen', function () {
    Storage::fake('public');

    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'test-key',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'openrouter.ai/api/v1/images')) {
            return Http::response([
                'data' => [['b64_json' => base64_encode('gallery-image'), 'media_type' => 'image/png']],
            ]);
        }

        return Http::response([], 404);
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'photo_paths' => null,
        'photo_url' => null,
        'listing_photo_urls' => ['https://example.com/one.jpg', 'https://example.com/two.jpg'],
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->postJson(route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'generate' => true,
            'slot' => 'photo',
            'index' => 2,
        ])
        ->assertOk()
        ->assertJsonCount(3, 'photos');

    expect($lead->fresh()->photo_paths[0])->toBe('https://example.com/one.jpg')
        ->and($lead->fresh()->photo_paths[1])->toBe('https://example.com/two.jpg')
        ->and($lead->fresh()->photo_paths)->toHaveCount(3)
        ->and($site->versions()->count())->toBe(1);
});

test('the visual editor can generate and attach an ai photo', function () {
    Storage::fake('public');

    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'test-key',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'openrouter.ai/api/v1/images')) {
            return Http::response([
                'data' => [['b64_json' => base64_encode('fake-image-bytes'), 'media_type' => 'image/png']],
            ]);
        }

        if (str_contains($request->url(), 'images/generations')) {
            return Http::response([
                'data' => [['b64_json' => base64_encode('fake-image-bytes')]],
            ]);
        }

        return Http::response([], 404);
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->postJson(route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'generate' => true,
            'slot' => 'photo',
            'prompt' => 'A friendly plumber at work in a bright kitchen',
        ])
        ->assertOk()
        ->assertJsonStructure(['photos']);

    expect($lead->fresh()->photo_paths)->toHaveCount(1);
});

test('the visual editor can generate a service card image without a prompt', function () {
    Storage::fake('public');

    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'test-key',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'openrouter.ai/api/v1/images')) {
            return Http::response([
                'data' => [['b64_json' => base64_encode('fake-service-image'), 'media_type' => 'image/png']],
            ]);
        }

        return Http::response([], 404);
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->postJson(route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'generate' => true,
            'slot' => 'service',
            'index' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('services.0', fn (?string $url) => is_string($url) && ! str_starts_with($url, 'data:'));

    expect($site->fresh()->content['services'][0]['image_path'] ?? null)->not->toBeEmpty();
});

test('the visual editor can improve an existing photo', function () {
    Storage::fake('public');

    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'test-key',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'chat/completions') || str_contains($request->url(), 'openrouter.ai/api/v1/images')) {
            return Http::response([
                'data' => [['b64_json' => base64_encode('improved-image-bytes'), 'media_type' => 'image/png']],
            ]);
        }

        return Http::response([], 404);
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'photo_paths' => ['teams/1/leads/1/original.jpg'],
    ]);
    Storage::disk('public')->put('teams/1/leads/1/original.jpg', 'original');
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->postJson(route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'improve' => true,
            'slot' => 'photo',
            'index' => 0,
        ])
        ->assertOk()
        ->assertJsonCount(1, 'photos');

    expect($lead->fresh()->photo_paths[0])->not->toBe('teams/1/leads/1/original.jpg');
});

test('sharpening a page hero uses the photo already on screen', function () {
    Storage::fake('public');

    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'test-key',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'chat/completions') || str_contains($request->url(), 'openrouter.ai/api/v1/images')) {
            return Http::response([
                'data' => [['b64_json' => base64_encode('sharper-hero'), 'media_type' => 'image/png']],
            ]);
        }

        return Http::response([], 404);
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'photo_paths' => ['teams/1/leads/1/shared.jpg'],
    ]);
    Storage::disk('public')->put('teams/1/leads/1/shared.jpg', 'shared');
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->postJson(route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'improve' => true,
            'slot' => 'home_hero',
        ])
        ->assertOk();

    expect($lead->fresh()->photo_paths)->toBe(['teams/1/leads/1/shared.jpg'])
        ->and($site->fresh()->content['images']['home_hero'] ?? null)->not->toBeEmpty()
        ->and($site->versions()->count())->toBe(1)
        ->and($site->versions()->first()->label)->toBe('Before changing the home hero photo');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'chat/completions')
        && str_contains($request->body(), base64_encode('shared')));
});

test('sharpening a listing photo edits that photo instead of asking for an upload', function () {
    Storage::fake('public');

    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'test-key',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'example.com/listing.jpg')) {
            return Http::response('listing-bytes', 200, ['Content-Type' => 'image/jpeg']);
        }

        if (str_contains($request->url(), 'chat/completions')) {
            return Http::response([
                'data' => [['b64_json' => base64_encode('sharper-listing'), 'media_type' => 'image/png']],
            ]);
        }

        return Http::response([], 404);
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'photo_paths' => null,
        'photo_url' => null,
        'listing_photo_urls' => ['https://example.com/listing.jpg'],
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->postJson(route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'improve' => true,
            'slot' => 'home_hero',
        ])
        ->assertOk();

    expect($lead->fresh()->photo_paths)->toBeNull()
        ->and($lead->fresh()->listing_photo_urls)->toBe(['https://example.com/listing.jpg'])
        ->and($site->fresh()->content['images']['home_hero'] ?? null)->not->toBeEmpty();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'chat/completions')
        && str_contains($request->body(), base64_encode('listing-bytes')));
});

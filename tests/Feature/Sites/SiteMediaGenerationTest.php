<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('the visual editor can generate and attach an ai photo', function () {
    Storage::fake('public');

    config(['services.openai.key' => 'test-key']);

    Http::fake(function (Request $request) {
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

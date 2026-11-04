<?php

use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteBuilder\SiteImageGenerationPlanner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('image planner picks wide aspect for overlay hero and tall aspect for split', function () {
    $planner = app(SiteImageGenerationPlanner::class);
    $overlayLead = Lead::factory()->create(['category' => 'House cleaning service', 'city' => 'Georgetown']);
    $splitLead = Lead::factory()->create(['category' => 'House cleaning service', 'city' => 'Georgetown']);

    $overlaySite = Site::factory()->create(['lead_id' => $overlayLead->id]);
    $overlayContent = $overlaySite->content;
    $overlayContent['variants'] = ['hero' => 'overlay'];
    $overlaySite->update(['content' => $overlayContent]);

    $splitSite = Site::factory()->create(['lead_id' => $splitLead->id]);
    $splitContent = $splitSite->content;
    $splitContent['variants'] = ['hero' => 'split'];
    $splitSite->update(['content' => $splitContent]);

    $overlayPlan = $planner->plan($overlaySite, $overlayLead, 'better hero image', 0);
    $splitPlan = $planner->plan($splitSite, $splitLead, 'better hero image', 0);

    expect($overlayPlan->aspectRatio)->toBe('16:9')
        ->and($splitPlan->aspectRatio)->toBe('3:4');
});

test('openrouter generation sends planner aspect ratio in the request', function () {
    Storage::fake('public');

    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'sk-or-test',
    ]);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'openrouter.ai/api/v1/images')) {
            $body = json_decode($request->body(), true);

            expect($body['aspect_ratio'] ?? null)->toBe('3:4');

            return Http::response([
                'data' => [['b64_json' => base64_encode('fake-image-bytes'), 'media_type' => 'image/png']],
            ]);
        }

        return Http::response([], 404);
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);
    $content = $site->content;
    $content['variants'] = ['hero' => 'split'];
    $site->update(['content' => $content]);

    $this->actingAs($user)
        ->postJson(route('sites.media.store', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            'generate' => true,
            'slot' => 'photo',
            'index' => 0,
            'prompt' => 'A friendly cleaning team in a bright home',
        ])
        ->assertOk();
});

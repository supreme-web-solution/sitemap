<?php

use App\Ai\Agents\SiteEditPlanner;
use App\Ai\Agents\SiteEditTurnAgent;
use App\Ai\Agents\SitePlanDesigner;
use App\Ai\Agents\SiteSectionDesignCritic;
use App\Ai\Agents\SiteSectionDesigner;
use App\Enums\SiteGenerationStatus;
use App\Enums\SiteSection;
use App\Enums\SiteTemplate;
use App\Enums\SiteVibe;
use App\Jobs\ApplySiteEdit;
use App\Jobs\GenerateSiteConcept;
use App\Models\Lead;
use App\Models\LeadReview;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteChatMessage;
use App\Models\User;
use App\Services\SiteBuilder\LeadPhotoGenerator;
use App\Services\SiteBuilder\SiteChatInstruction;
use App\Services\SiteBuilder\SiteEditApplier;
use App\Services\SiteBuilder\SiteRenderer;
use App\Services\SiteBuilder\SiteSectionCss;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\Data\ToolCall;

function fakeSiteEditTurnApply(): ToolCall
{
    return new ToolCall(
        'tool_edit_turn',
        'SubmitSiteEditTurn',
        ['turn_json' => json_encode([
            'mode' => 'apply_edit',
            'message' => '',
            'apply_instruction' => null,
        ], JSON_THROW_ON_ERROR)],
    );
}

function fakeSiteEditPlanToolCall(array $overrides = []): ToolCall
{
    $plan = [
        'reasoning' => 'Apply the user request.',
        'copy_instruction' => null,
        'design_css_targets' => [],
        'design_instruction' => '',
        'sections_add' => [],
        'sections_remove' => [],
        'layout_variants' => (object) [],
        'document_ops' => [],
        ...$overrides,
    ];

    return new ToolCall(
        'tool_edit_plan',
        'SubmitSiteEditPlan',
        ['plan_json' => json_encode($plan, JSON_THROW_ON_ERROR)],
    );
}

function fakeSitePlanToolCallForEdit(array $overrides = []): ToolCall
{
    $plan = [
        'template' => 'modern-clean',
        'accent' => '#0891b2',
        'headline' => 'Cleaning in Austin',
        'tagline' => 'Reliable local cleaning.',
        'cta_label' => 'Get a quote',
        'about' => 'We keep homes spotless.',
        'sections' => ['services', 'about', 'contact'],
        'services' => [
            ['title' => 'Home cleaning', 'description' => 'Regular visits on your schedule.'],
            ['title' => 'Deep cleaning', 'description' => 'Top-to-bottom refresh.'],
            ['title' => 'Move-out cleaning', 'description' => 'Leave the keys with confidence.'],
        ],
        'faqs' => [],
        ...$overrides,
    ];

    return new ToolCall(
        'tool_1',
        'SubmitSitePlan',
        ['plan_json' => json_encode($plan, JSON_THROW_ON_ERROR)],
    );
}

beforeEach(function () {
    config(['sites.use_edit_executor' => false]);

    SiteEditTurnAgent::fake(fn () => fakeSiteEditTurnApply());

    SiteSectionDesignCritic::fake(fn () => new ToolCall(
        'tool_design_review',
        'SubmitSectionDesignReview',
        ['passed' => true, 'feedback' => 'Matches the request.', 'revision_brief' => ''],
    ));
});

function fakeSectionDesignCss(string $css, string $summary = 'Balanced the footer columns.'): void
{
    SiteSectionDesigner::fake(fn () => new ToolCall(
        'tool_1',
        'SubmitSectionCssPatch',
        ['css' => $css, 'summary' => $summary],
    ));
}

test('requesting three designs queues one job per vibe on the ai queue', function () {
    Bus::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('leads.concepts.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead]))
        ->assertRedirect();

    expect($lead->fresh()->concepts_status)->toBe(SiteGenerationStatus::Queued);

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3
        && $batch->jobs->every(fn ($job) => $job instanceof GenerateSiteConcept));
});

test('three concepts are generated and choosing one becomes the website', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'name' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
    ]);

    $this->actingAs($user)
        ->post(route('leads.concepts.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead]))
        ->assertRedirect();

    expect($lead->concepts()->count())->toBe(3)
        ->and($lead->fresh()->concepts_status)->toBe(SiteGenerationStatus::Idle)
        ->and($lead->concepts()->pluck('template')->map->value->sort()->values()->all())
        ->toBe(collect(SiteTemplate::cases())->map->value->sort()->values()->all());

    $concept = $lead->concepts()->where('template', SiteTemplate::WarmStudio)->firstOrFail();

    $this->get(route('sites.concepts.preview', $concept))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Austin', false);

    $this->actingAs($user)
        ->post(route('leads.concepts.choose', ['current_team' => $user->currentTeam->slug, 'lead' => $lead, 'concept' => $concept]))
        ->assertRedirect(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $lead->fresh()->site]));

    expect($lead->fresh())
        ->vibe->toBe(SiteVibe::Warm)
        ->and($lead->site->template)->toBe(SiteTemplate::WarmStudio);
});

test('each concept uses a catalog design and regenerating gives different designs', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'name' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
    ]);
    $store = fn () => $this->actingAs($user)
        ->post(route('leads.concepts.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead]))
        ->assertRedirect();

    $store();
    $first = $lead->concepts()->pluck('design_template_id')->all();

    $store();
    $second = $lead->concepts()->pluck('design_template_id')->all();

    expect($first)->toHaveCount(3)->each->not->toBeNull()
        ->and($second)->toHaveCount(3)->each->not->toBeNull()
        ->and(array_intersect($first, $second))->toBeEmpty();

    $concept = $lead->concepts()->firstOrFail();

    $this->actingAs($user)
        ->post(route('leads.concepts.choose', ['current_team' => $user->currentTeam->slug, 'lead' => $lead, 'concept' => $concept]))
        ->assertRedirect();

    expect($lead->fresh()->site->design_template_id)->toBe($concept->design_template_id);
});

test('preview edits target footer without design keywords', function () {
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-test']);

    $css = ".site-footer { padding: 1rem; }\n.footer-main { gap: 2rem; }";

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);
    $plan = SiteEditApplier::document($site);

    Http::fake(function (Request $request) use ($plan) {
        $payload = json_decode($request->body(), true);
        $tool = $payload['tools'][0]['name'] ?? '';

        if ($tool === 'submit_site_plan') {
            return Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'submit_site_plan',
                    'input' => $plan,
                ]],
            ]);
        }

        return Http::response(['content' => []]);
    });

    fakeSectionDesignCss($css, 'Tightened footer spacing.');

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'fix spacing here',
            'design_target' => 'footer',
            'preview_edits' => [[
                'region' => 'footer',
                'selector' => '.footer-main .footer-col',
                'tag' => 'nav',
                'text' => 'Explore',
            ]],
        ])
        ->assertRedirect();

    expect($site->fresh()->content['section_css']['footer'] ?? '')->toContain('.footer-main');
});

test('headline change via document_ops does not call the copy planner', function () {
    config(['services.anthropic.key' => 'test-key']);

    SiteEditPlanner::fake(fn () => fakeSiteEditPlanToolCall([
        'copy_instruction' => null,
        'document_ops' => [
            ['op' => 'set', 'path' => 'headline', 'value' => 'Georgetown Cleaning Pros'],
        ],
    ]));

    SitePlanDesigner::fake(function () {
        throw new RuntimeException('Copy planner should not run when document_ops set the headline.');
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'Change the headline to Georgetown Cleaning Pros',
        ])
        ->assertRedirect();

    expect($site->fresh()->content['headline'])->toBe('Georgetown Cleaning Pros');
});

test('centre hero request applies deterministic css without the section designer agent', function () {
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-test']);

    SiteEditPlanner::fake(fn () => fakeSiteEditPlanToolCall([
        'design_css_targets' => ['hero'],
        'design_instruction' => 'Center hero content',
    ]));

    SiteSectionDesigner::fake(function () {
        throw new RuntimeException('Section designer should not run when a layout preset matches.');
    });

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'center the hero section content over the background photo',
            'design_target' => 'hero',
        ])
        ->assertRedirect();

    $css = $site->fresh()->content['section_css']['hero'] ?? '';

    expect($css)->toContain('.hero .container')
        ->and($css)->toContain('justify-content: center');
});

test('footer design chat writes scoped css via anthropic', function () {
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-test']);

    $css = ".site-footer { padding-top: 2.5rem; }\n.footer-main { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 2rem; align-items: start; }\n.footer-col h3 { margin-bottom: 1rem; }";

    expect(SiteSectionCss::validate('footer', $css))->toBeEmpty();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);
    $plan = SiteEditApplier::document($site);

    Http::fake(function (Request $request) use ($plan) {
        $payload = json_decode($request->body(), true);
        $tool = $payload['tools'][0]['name'] ?? '';

        if ($tool === 'submit_site_plan') {
            return Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'submit_site_plan',
                    'input' => $plan,
                ]],
            ]);
        }

        return Http::response(['content' => []]);
    });

    fakeSectionDesignCss($css, 'Balanced footer columns.');

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'give me a modern balanced footer design',
            'design_target' => 'footer',
        ])
        ->assertRedirect();

    $assistant = SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_ASSISTANT)->first();

    expect($assistant->status)->toBe(SiteChatMessage::STATUS_DONE, $assistant->body)
        ->and($site->fresh()->content['section_css']['footer'] ?? '')->toContain('.footer-main')
        ->and($assistant->body)->toContain('custom CSS');
});

test('asking to redesign a section cycles its layout preset when using inspector prompt', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'section' => 'services',
            'redesign' => true,
        ])
        ->assertRedirect();

    expect($site->fresh()->content['variants']['services'] ?? null)->toBe('list')
        ->and($site->fresh()->template)->toBe($site->template);
});

test('chat edits finish in the same request as the chat post', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'Change the headline to "Local Roofing Experts"',
        ])
        ->assertRedirect();

    expect($site->fresh()->content['headline'])->toBe('Local Roofing Experts')
        ->and(SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_ASSISTANT)->value('status'))
        ->toBe(SiteChatMessage::STATUS_DONE);
});

test('structural intent adds reviews even when the ai plan is unchanged', function () {
    SiteEditPlanner::fake(fn () => fakeSiteEditPlanToolCall([
        'sections_add' => ['reviews'],
        'copy_instruction' => null,
    ]));
    SitePlanDesigner::fake(fn () => fakeSitePlanToolCallForEdit());

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['reviews_count' => 1, 'rating' => 5]);
    LeadReview::factory()->for($lead)->create(['is_visible' => true, 'rating' => 5]);
    $sections = array_values(array_filter(
        SiteSection::defaultOrder(),
        fn (string $section) => $section !== SiteSection::Reviews->value,
    ));
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => array_merge(Site::factory()->make()->content, ['sections' => $sections]),
    ]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'add review section in the home page',
        ])
        ->assertRedirect();

    expect($site->fresh()->content['sections'])->toContain(SiteSection::Reviews->value);
});

test('site chat accepts a screenshot attachment with instructions', function () {
    Storage::fake('public');
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-test']);

    SiteEditTurnAgent::fake(fn () => new ToolCall(
        'tool_edit_turn',
        'SubmitSiteEditTurn',
        ['turn_json' => json_encode([
            'mode' => 'reply_only',
            'message' => 'I see the hero headline in your screenshot.',
            'apply_instruction' => null,
        ], JSON_THROW_ON_ERROR)],
    ));

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'Center the headline like in this screenshot',
            'attachment' => UploadedFile::fake()->image('hero.png', 800, 600),
        ])
        ->assertRedirect();

    $userMessage = SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_USER)->first();

    expect($userMessage->attachment_path)->not->toBeNull()
        ->and($userMessage->attachmentPaths())->toHaveCount(1)
        ->and(Storage::disk('public')->exists((string) $userMessage->attachment_path))->toBeTrue();
});

test('site chat accepts multiple screenshot attachments', function () {
    Storage::fake('public');
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-test']);

    SiteEditTurnAgent::fake(fn () => new ToolCall(
        'tool_edit_turn',
        'SubmitSiteEditTurn',
        ['turn_json' => json_encode([
            'mode' => 'apply_edit',
            'message' => 'Updating hero and services from your screenshots.',
            'apply_instruction' => 'Make hero full width; improve services cards with images.',
        ], JSON_THROW_ON_ERROR)],
    ));

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'Match these two areas',
            'attachments' => [
                UploadedFile::fake()->image('hero-ref.png', 800, 600),
                UploadedFile::fake()->image('services-ref.png', 800, 600),
            ],
        ])
        ->assertRedirect();

    $userMessage = SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_USER)->first();

    expect($userMessage->attachmentPaths())->toHaveCount(2)
        ->and(collect($userMessage->attachmentPaths())->every(
            fn (string $path) => Storage::disk('public')->exists($path),
        ))->toBeTrue();
});

test('auto image driver prefers openai when its key is set', function () {
    config([
        'sites.image_driver' => 'auto',
        'services.openrouter.key' => 'sk-or-test',
        'services.openai.key' => 'sk-openai',
    ]);

    expect(LeadPhotoGenerator::resolveDriver())->toBe('openai');
});

test('attached image can be placed as the hero photo from chat', function () {
    Storage::fake('public');
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-test']);

    SiteEditTurnAgent::fake(fn () => new ToolCall(
        'tool_edit_turn',
        'SubmitSiteEditTurn',
        ['turn_json' => json_encode([
            'mode' => 'apply_attached_image',
            'message' => 'Using your photo for the hero.',
            'apply_instruction' => null,
            'attached_image_placement' => 'hero',
        ], JSON_THROW_ON_ERROR)],
    ));

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['photo_paths' => []]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'Use this image as the hero photo',
            'attachment' => UploadedFile::fake()->image('my-logo.png', 1200, 800),
        ])
        ->assertRedirect();

    $paths = $lead->fresh()->photo_paths ?? [];

    expect($paths)->toHaveCount(1)
        ->and(Storage::disk('public')->exists($paths[0]))->toBeTrue()
        ->and($site->chatMessages()->where('role', SiteChatMessage::ROLE_ASSISTANT)->value('body'))
        ->toContain('hero');
});

test('hero image regeneration uses openrouter from chat', function () {
    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'sk-or-test',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/images' => Http::response([
            'data' => [['b64_json' => base64_encode('fake-png-bytes'), 'media_type' => 'image/png']],
        ], 200),
    ]);

    SiteEditTurnAgent::fake(fn () => new ToolCall(
        'tool_edit_turn',
        'SubmitSiteEditTurn',
        ['turn_json' => json_encode([
            'mode' => 'generate_hero_image',
            'message' => 'Here is a fresher hero photo for your overlay layout.',
            'apply_instruction' => null,
            'image_hint' => 'bright modern living room after cleaning',
        ], JSON_THROW_ON_ERROR)],
    ));

    Storage::fake('public');

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['category' => 'House cleaning service', 'city' => 'Georgetown']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'regenerate the image in the hero section, I need a better image',
        ])
        ->assertRedirect();

    expect($lead->fresh()->photo_paths)->not->toBeEmpty()
        ->and($site->chatMessages()->where('role', SiteChatMessage::ROLE_ASSISTANT)->value('body'))
        ->toContain('hero photo');
});

test('multi-part hero and services request still applies overlay and service photos', function () {
    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'sk-or-test',
        'sites.use_edit_executor' => false,
    ]);

    Http::fake([
        'openrouter.ai/api/v1/images' => Http::response([
            'data' => [['b64_json' => base64_encode('fake-png-bytes'), 'media_type' => 'image/png']],
        ], 200),
    ]);

    SiteEditTurnAgent::fake(fn () => new ToolCall(
        'tool_edit_turn',
        'SubmitSiteEditTurn',
        ['turn_json' => json_encode([
            'mode' => 'generate_hero_image',
            'message' => 'Generating hero and service photos.',
            'apply_instruction' => 'Generate unique AI images for each service card.',
            'image_hint' => 'roofing work',
        ], JSON_THROW_ON_ERROR)],
    ));

    Storage::fake('public');

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'category' => 'Roofing contractor',
        'city' => 'Dallas',
        'photo_paths' => [],
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => [
            'accent' => '#2563eb',
            'headline' => 'Roofing in Dallas',
            'tagline' => 'Local roof repair and inspection.',
            'cta_label' => 'Get a quote',
            'about' => 'We keep roofs watertight.',
            'sections' => ['services', 'about', 'contact'],
            'variants' => ['hero' => 'minimal'],
            'services' => [
                ['title' => 'Roof repair', 'description' => 'Fix leaks and damaged shingles.'],
                ['title' => 'Roof inspection', 'description' => 'Check the condition of the roof.'],
            ],
            'faqs' => [],
        ],
    ]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => "there should be background image in the hero section, not plain background\n\n2. in the services section, generate unique images for each of those services",
        ])
        ->assertRedirect();

    $site->refresh();
    $lead->refresh();

    expect($lead->photo_paths)->not->toBeEmpty()
        ->and($site->content['variants']['hero'] ?? null)->toBe('overlay')
        ->and($site->content['services'][0]['image_path'] ?? null)->not->toBeEmpty()
        ->and($site->content['services'][1]['image_path'] ?? null)->not->toBeEmpty();
});

test('hero image openrouter quota errors reply in chat without a server error', function () {
    config([
        'sites.image_driver' => 'openrouter',
        'services.openrouter.key' => 'sk-or-test',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/images' => Http::response([
            'error' => ['message' => 'You have no credits remaining.'],
        ], 429),
    ]);

    SiteEditTurnAgent::fake(fn () => new ToolCall(
        'tool_edit_turn',
        'SubmitSiteEditTurn',
        ['turn_json' => json_encode([
            'mode' => 'generate_hero_image',
            'message' => 'Generating a new hero photo for you.',
            'apply_instruction' => null,
            'image_hint' => null,
        ], JSON_THROW_ON_ERROR)],
    ));

    Storage::fake('public');

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'help me generate a better hero section image',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $assistant = SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_ASSISTANT)->first();

    expect($assistant->status)->toBe(SiteChatMessage::STATUS_DONE)
        ->and($assistant->body)->toContain('not available');
});

test('a follow-up question gets a conversational reply without a site snapshot', function () {
    SiteEditTurnAgent::fake(fn () => new ToolCall(
        'tool_edit_turn',
        'SubmitSiteEditTurn',
        ['turn_json' => json_encode([
            'mode' => 'reply_only',
            'message' => 'Customer reviews is on the homepage after Why choose us. Fetch Google reviews on the lead if the block looks empty.',
            'apply_instruction' => null,
        ], JSON_THROW_ON_ERROR)],
    ));

    $user = User::factory()->create();
    $site = Site::factory()->create(['lead_id' => Lead::factory()->for($user->currentTeam)->create()->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'where is the testimonial section, cos i cant find it',
        ])
        ->assertRedirect();

    expect($site->versions()->count())->toBe(0)
        ->and($site->chatMessages()->where('role', SiteChatMessage::ROLE_ASSISTANT)->value('body'))
        ->toContain('Customer reviews');
});

test('testimonial section phrasing maps to the reviews block', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['reviews_count' => 1, 'rating' => 5]);
    LeadReview::factory()->for($lead)->create(['is_visible' => true, 'rating' => 5]);
    $sections = array_values(array_filter(
        SiteSection::defaultOrder(),
        fn (string $section) => $section !== SiteSection::Reviews->value,
    ));
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => array_merge(Site::factory()->make()->content, ['sections' => $sections]),
    ]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'add a testimonial section in the home page',
        ])
        ->assertRedirect();

    expect($site->fresh()->content['sections'])->toContain(SiteSection::Reviews->value);
});

test('chat can add the customer reviews section to the homepage', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['reviews_count' => 1, 'rating' => 5]);
    LeadReview::factory()->for($lead)->create(['is_visible' => true, 'rating' => 5]);
    $sections = array_values(array_filter(
        SiteSection::defaultOrder(),
        fn (string $section) => $section !== SiteSection::Reviews->value,
    ));
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'content' => array_merge(Site::factory()->make()->content, ['sections' => $sections]),
    ]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'add review section in the home page',
        ])
        ->assertRedirect();

    expect($site->fresh()->content['sections'])->toContain(SiteSection::Reviews->value);
});

test('a chat edit updates the headline and snapshots a version', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'Change the headline to "Local Roofing Experts"',
        ])
        ->assertRedirect();

    expect($site->fresh()->content['headline'])->toBe('Local Roofing Experts')
        ->and($site->versions()->count())->toBe(1)
        ->and(SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_ASSISTANT)->value('status'))->toBe(SiteChatMessage::STATUS_DONE);
});

test('ai editing is blocked when the plan leaves it out', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->forceFill(['plan_id' => Plan::factory()->create(['features' => []])->id])->save();
    $lead = Lead::factory()->for($team)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $team->slug, 'site' => $site]), [
            'body' => 'Change the headline to "Nope"',
        ]);

    expect(SiteChatMessage::count())->toBe(0);
    Queue::assertNothingPushed();

    $this->actingAs($user)
        ->post(route('leads.concepts.store', ['current_team' => $team->slug, 'lead' => $lead]));

    expect($lead->fresh()->concepts_status)->toBe(SiteGenerationStatus::Idle);
});

test('user can cancel a stuck pending chat edit', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $site->chatMessages()->create([
        'user_id' => $user->id,
        'role' => SiteChatMessage::ROLE_ASSISTANT,
        'body' => 'Applying your edit…',
        'status' => SiteChatMessage::STATUS_PENDING,
    ]);

    $this->actingAs($user)
        ->delete(route('sites.chat.destroy', ['current_team' => $user->currentTeam->slug, 'site' => $site]))
        ->assertRedirect();

    expect($site->chatMessages()->where('status', SiteChatMessage::STATUS_PENDING)->exists())->toBeFalse()
        ->and($site->chatMessages()->where('role', SiteChatMessage::ROLE_ASSISTANT)->value('body'))
        ->toContain('cancelled');
});

test('a second chat edit is rejected while one is still pending', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);
    $args = ['current_team' => $user->currentTeam->slug, 'site' => $site];

    $site->chatMessages()->create([
        'user_id' => $user->id,
        'role' => SiteChatMessage::ROLE_ASSISTANT,
        'body' => 'Applying your edit…',
        'status' => SiteChatMessage::STATUS_PENDING,
    ]);

    $this->actingAs($user)->post(route('sites.chat.store', $args), ['body' => 'Change the headline to "Second"'])
        ->assertSessionHasErrors('body');

    expect(SiteChatMessage::where('role', SiteChatMessage::ROLE_USER)->count())->toBe(0);
});

test('section css validator rejects page-wide selectors', function () {
    expect(SiteSectionCss::validate('footer', 'body { color: red; }'))->not->toBeEmpty();
});

test('whole site design targets every visible section key', function () {
    config(['services.anthropic.key' => 'test-key']);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);
    $renderer = app(SiteRenderer::class);

    $targets = SiteChatInstruction::designTargets('restyle the whole site to feel premium', null, $site, $renderer);

    expect($targets)->toContain('header', 'hero', 'footer')
        ->and($targets)->toBe($renderer->designTargetKeys($site));
});

test('failed site chat edit shows retry and requeues without extra usage', function () {
    Queue::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $userMessage = $site->chatMessages()->create([
        'user_id' => $user->id,
        'role' => SiteChatMessage::ROLE_USER,
        'body' => 'Make the hero full width',
        'status' => SiteChatMessage::STATUS_DONE,
    ]);

    $failed = $site->chatMessages()->create([
        'user_id' => $user->id,
        'role' => SiteChatMessage::ROLE_ASSISTANT,
        'body' => 'The design assistant could not respond. Try again.',
        'status' => SiteChatMessage::STATUS_FAILED,
        'user_message_id' => $userMessage->id,
    ]);

    $this->actingAs($user)
        ->post(route('sites.chat.retry', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'message_id' => $failed->id,
        ])
        ->assertRedirect();

    Queue::assertPushed(ApplySiteEdit::class);

    expect(SiteChatMessage::query()->where('status', SiteChatMessage::STATUS_PENDING)->count())->toBe(1)
        ->and(SiteChatMessage::attemptCountForUserMessage($site->id, $userMessage->id))->toBe(2);
});

test('site chat retry is blocked after three attempts', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create(['lead_id' => Lead::factory()->for($user->currentTeam)->create()->id]);

    $userMessage = $site->chatMessages()->create([
        'user_id' => $user->id,
        'role' => SiteChatMessage::ROLE_USER,
        'body' => 'Update services',
        'status' => SiteChatMessage::STATUS_DONE,
    ]);

    foreach (range(1, 3) as $attempt) {
        $site->chatMessages()->create([
            'user_id' => $user->id,
            'role' => SiteChatMessage::ROLE_ASSISTANT,
            'body' => 'Failed attempt '.$attempt,
            'status' => SiteChatMessage::STATUS_FAILED,
            'user_message_id' => $userMessage->id,
        ]);
    }

    $latestFailed = SiteChatMessage::query()->latest('id')->first();

    $this->actingAs($user)
        ->post(route('sites.chat.retry', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'message_id' => $latestFailed->id,
        ])
        ->assertSessionHasErrors('message_id');
});

test('design edit without api key fails clearly', function () {
    config(['services.anthropic.key' => null]);

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('sites.chat.store', ['current_team' => $user->currentTeam->slug, 'site' => $site]), [
            'body' => 'redesign the footer to look modern',
        ])
        ->assertRedirect();

    $assistant = SiteChatMessage::query()->where('role', SiteChatMessage::ROLE_ASSISTANT)->first();
    expect($assistant->status)->toBe(SiteChatMessage::STATUS_FAILED)
        ->and($assistant->body)->toContain('ANTHROPIC');
});

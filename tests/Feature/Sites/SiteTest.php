<?php

use App\Enums\LeadStatus;
use App\Enums\SiteGenerationStatus;
use App\Enums\SiteTemplate;
use App\Enums\SiteVibe;
use App\Jobs\GenerateLeadSite;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

$validContent = [
    'template' => 'editorial-dark',
    'accent' => '#ff5500',
    'headline' => 'Austin’s Favourite Plumbers',
    'tagline' => 'Same-day help across Austin.',
    'cta_label' => 'Call Today',
    'about' => 'A family team serving Austin.',
    'sections' => ['about', 'services', 'contact'],
    'services' => [['title' => 'Repairs', 'description' => 'Leaks fixed fast.']],
    'faqs' => [],
];

test('requesting a website queues its generation', function () {
    Queue::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);

    $this->actingAs($user)
        ->from(route('leads.index', ['current_team' => $user->currentTeam->slug]))
        ->post(route('leads.site.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertRedirect(route('leads.index', ['current_team' => $user->currentTeam->slug]));

    expect($lead->fresh()->site_generation_status)->toBe(SiteGenerationStatus::Queued);
    Queue::assertPushed(GenerateLeadSite::class, fn (GenerateLeadSite $job) => $job->lead->is($lead));
});

test('a website is generated for a lead', function () {
    $lead = Lead::factory()->create([
        'name' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
        'rating' => 4.9,
        'reviews_count' => 142,
        'site_generation_status' => SiteGenerationStatus::Queued,
    ]);

    GenerateLeadSite::dispatchSync($lead);

    $site = $lead->site()->sole();

    expect($site)
        ->team_id->toBe($lead->team_id)
        ->template->toBe(SiteTemplate::ModernClean)
        ->slug->toStartWith('austin-pro-plumbing-');
    expect($site->content['headline'])->toBe('Trusted Plumbing in Austin');
    expect($site->content['about'])->toContain('4.9 out of 5 across 142 Google reviews');
    expect($lead->fresh())
        ->status->toBe(LeadStatus::SiteGenerated)
        ->site_generation_status->toBe(SiteGenerationStatus::Idle);
});

test('the owner chosen vibe picks the template', function () {
    $lead = Lead::factory()->create(['category' => 'Plumber', 'vibe' => SiteVibe::Warm]);

    GenerateLeadSite::dispatchSync($lead);

    expect($lead->site()->sole()->template)->toBe(SiteTemplate::WarmStudio);
});

test('a failed generation is shown on the lead', function () {
    $lead = Lead::factory()->create(['site_generation_status' => SiteGenerationStatus::Queued]);

    (new GenerateLeadSite($lead))->failed(new RuntimeException('boom'));

    expect($lead->fresh())
        ->site_generation_status->toBe(SiteGenerationStatus::Failed)
        ->site_generation_error->toBe('The website could not be generated. Please try again.');
});

test('generated copy does not claim reviews the business does not have', function () {
    $lead = Lead::factory()->create([
        'category' => 'Florist',
        'rating' => null,
        'reviews_count' => 0,
        'address' => null,
        'phone' => null,
    ]);

    GenerateLeadSite::dispatchSync($lead);

    $content = $lead->site()->sole()->content;

    expect($content['about'])->not->toContain('reviews');
    expect($content['faqs'])->toBe([]);
    expect($content['services'][0]['title'])->toBe('Florist');
});

test('regenerating a website keeps its public link and pipeline stage', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    $site = Site::factory()->create(['lead_id' => $lead->id, 'slug' => 'keep-this-slug']);

    GenerateLeadSite::dispatchSync($lead);

    expect(Site::sole())
        ->id->toBe($site->id)
        ->slug->toBe('keep-this-slug');
    expect($lead->fresh()->status)->toBe(LeadStatus::Contacted);
});

test('a website cannot be generated for another team lead', function () {
    Queue::fake();

    $user = User::factory()->create();
    $otherLead = Lead::factory()->create();

    $this->actingAs($user)
        ->post(route('leads.site.store', ['current_team' => $user->currentTeam->slug, 'lead' => $otherLead->id]))
        ->assertNotFound();

    expect($otherLead->fresh()->site_generation_status)->toBe(SiteGenerationStatus::Idle);
    Queue::assertNothingPushed();
});

test('the sites page lists only the current team websites', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'Mine']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);
    Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.index', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Index')
            ->has('sites.data', 1)
            ->where('sites.data.0.name', 'Mine')
            ->where('sites.data.0.previewUrl', route('sites.preview', $site->slug)));
});

test('the editor shows the website content', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->get(route('sites.edit', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sites/Edit')
            ->where('site.content.headline', $site->content['headline'])
            ->has('templates', count(SiteTemplate::cases())));
});

test('website edits are saved', function () use ($validContent) {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), $validContent)
        ->assertRedirect();

    $site->refresh();

    expect($site->template)->toBe(SiteTemplate::EditorialDark);
    expect($site->content)->toBe([
        'accent' => '#ff5500',
        'headline' => 'Austin’s Favourite Plumbers',
        'tagline' => 'Same-day help across Austin.',
        'cta_label' => 'Call Today',
        'about' => 'A family team serving Austin.',
        'sections' => ['about', 'services', 'contact'],
        'services' => [['title' => 'Repairs', 'description' => 'Leaks fixed fast.']],
        'faqs' => [],
    ]);
});

test('website sections must come from the section library', function () use ($validContent) {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            ...$validContent,
            'sections' => ['services', 'testimonials'],
        ])
        ->assertSessionHasErrors('sections.1');
});

test('the brand colour must be a hex colour', function () use ($validContent) {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            ...$validContent,
            'accent' => 'red; } body { display:none',
        ])
        ->assertSessionHasErrors(['accent' => 'The accent field format is invalid.']);
});

test('a website needs at least one service', function () use ($validContent) {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]), [
            ...$validContent,
            'services' => [],
        ])
        ->assertSessionHasErrors(['services' => 'The services field is required.']);
});

test('another team website cannot be edited through your team', function () use ($validContent) {
    $user = User::factory()->create();
    $otherSite = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.update', ['current_team' => $user->currentTeam->slug, 'site' => $otherSite->id]), $validContent)
        ->assertNotFound();
});

test('a website can be exported as a zip of static html', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['team_id' => $user->currentTeam->id, 'name' => 'Austin Pro Plumbing']);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $response = $this->actingAs($user)
        ->get(route('sites.export', ['current_team' => $user->currentTeam->slug, 'site' => $site->id]));

    $response->assertOk()->assertDownload('austin-pro-plumbing-website.zip');

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $html = $zip->getFromName('index.html');
    $zip->close();

    expect($html)
        ->toContain('Austin Pro Plumbing')
        ->toContain($site->content['headline'])
        ->not->toContain('Claim this website');
});

test('another team website cannot be exported', function () {
    $user = User::factory()->create();
    $otherSite = Site::factory()->create();

    $this->actingAs($user)
        ->get(route('sites.export', ['current_team' => $user->currentTeam->slug, 'site' => $otherSite->id]))
        ->assertNotFound();
});

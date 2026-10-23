<?php

use App\Enums\LeadActivityType;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Services\Outreach\MergeFieldRenderer;
use Inertia\Testing\AssertableInertia as Assert;

test('visiting email studio creates starter templates once', function () {
    $user = User::factory()->create();
    $url = route('email-templates.index', ['current_team' => $user->currentTeam->slug]);

    $this->actingAs($user)->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('outreach/Templates')->has('templates', 3)->has('mergeFields'));

    $this->actingAs($user)->get($url);

    expect($user->currentTeam->emailTemplates()->count())->toBe(3);
});

test('templates can be created, updated and deleted', function () {
    $user = User::factory()->create();
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)
        ->post(route('email-templates.store', ['current_team' => $slug]), [
            'name' => 'Cold intro',
            'subject' => 'Hello {{business_name}}',
            'body' => 'Hi from {{sender_name}}',
        ])->assertRedirect();

    $template = EmailTemplate::where('name', 'Cold intro')->sole();

    $this->actingAs($user)
        ->put(route('email-templates.update', ['current_team' => $slug, 'emailTemplate' => $template->id]), [
            'name' => 'Cold intro v2',
            'subject' => 'Hi {{business_name}}',
            'body' => 'Body {{preview_url}}',
        ])->assertRedirect();

    expect($template->fresh()->name)->toBe('Cold intro v2');

    $this->actingAs($user)
        ->delete(route('email-templates.destroy', ['current_team' => $slug, 'emailTemplate' => $template->id]))
        ->assertRedirect();

    expect(EmailTemplate::find($template->id))->toBeNull();
});

test('unknown merge fields are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('email-templates.store', ['current_team' => $user->currentTeam->slug]), [
            'name' => 'Broken',
            'subject' => 'Hello {{nonsense}}',
            'body' => 'Body',
        ])->assertSessionHasErrors('subject');
});

test("another team's template cannot be changed", function () {
    $user = User::factory()->create();
    $foreign = EmailTemplate::factory()->create();

    $this->actingAs($user)
        ->delete(route('email-templates.destroy', ['current_team' => $user->currentTeam->slug, 'emailTemplate' => $foreign->id]))
        ->assertNotFound();
});

test('merge fields are filled from the lead, its preview and the sender', function () {
    $user = User::factory()->create(['name' => 'Alex Agent']);
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'name' => 'Austin Pro Plumbing',
        'category' => 'Plumber',
        'city' => 'Austin',
        'website_audit' => ['score' => 10, 'error' => null, 'checks' => [
            ['key' => 'viewport', 'label' => 'Mobile', 'status' => 'fail', 'detail' => 'No mobile viewport tag.'],
        ]],
    ]);
    $site = Site::factory()->create(['lead_id' => $lead->id]);

    $text = app(MergeFieldRenderer::class)->render(
        '{{business_name}} / {{category}} in {{ city }} / {{preview_url}} / {{top_issue}} / {{sender_name}} / {{unknown}}',
        $lead->fresh(),
        $user,
    );

    expect($text)->toBe('Austin Pro Plumbing / Plumber in Austin / '.route('sites.preview', $site->slug).' / No mobile viewport tag. / Alex Agent / {{unknown}}');
});

test('the lead profile includes timeline, verification and outreach data', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $lead->recordActivity(LeadActivityType::Note, 'Called, no answer');

    $this->actingAs($user)
        ->get(route('leads.edit', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/Edit')
            ->has('verification.items')
            ->has('verification.readiness')
            ->has('activities', 1)
            ->where('activities.0.summary', 'Called, no answer')
            ->has('emailTemplates', 3)
            ->has('mergeValues.business_name')
            ->has('lead.opportunityBreakdown', 5));
});

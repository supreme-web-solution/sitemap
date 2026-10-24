<?php

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Mail\OutreachEmail;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('the board groups leads into a column per stage and only shows the current team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Lead::factory()->count(2)->for($team)->create(['status' => LeadStatus::New]);
    Lead::factory()->for($team)->create(['status' => LeadStatus::Won]);
    Lead::factory()->create(['status' => LeadStatus::Won]);

    $this->actingAs($user)
        ->get(route('leads.board', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/Board')
            ->has('columns', count(LeadStatus::cases()))
            ->where('columns.0.status', 'new')
            ->where('columns.0.count', 2)
            ->has('columns.0.leads', 2)
            ->where('columns.4.status', 'won')
            ->where('columns.4.count', 1));
});

test('the board only loads a first page per column but reports the full count', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Lead::factory()->count(20)->for($team)->create(['status' => LeadStatus::New]);

    $this->actingAs($user)
        ->get(route('leads.board', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.count', 20)
            ->has('columns.0.leads', 15)
            ->where('step', 15)
            ->where('totalLeads', 20));

    $this->actingAs($user)
        ->get(route('leads.board', ['current_team' => $team->slug, 'limits' => ['new' => 20]]))
        ->assertInertia(fn (Assert $page) => $page->has('columns.0.leads', 20));
});

test('board filters narrow both the cards and the column counts', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Lead::factory()->for($team)->create(['name' => 'Austin Plumbing', 'city' => 'Austin', 'status' => LeadStatus::New]);
    Lead::factory()->for($team)->create(['name' => 'Dallas Roofing', 'city' => 'Dallas', 'status' => LeadStatus::New]);
    Lead::factory()->for($team)->create(['name' => 'Austin Dental', 'city' => 'Austin', 'status' => LeadStatus::Contacted]);
    Lead::factory()->create(['name' => 'Austin Other Team', 'city' => 'Austin', 'status' => LeadStatus::New]);

    $this->actingAs($user)
        ->get(route('leads.board', ['current_team' => $team->slug, 'search' => 'Austin']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.count', 1)
            ->where('columns.0.leads.0.name', 'Austin Plumbing')
            ->where('columns.2.count', 1)
            ->where('filters.search', 'Austin')
            ->where('totalLeads', 3));
});

test('board filters can show only follow-ups that are due', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Lead::factory()->for($team)->create(['name' => 'Due Lead', 'next_follow_up_at' => today()->subDay()]);
    Lead::factory()->for($team)->create(['name' => 'Later Lead', 'next_follow_up_at' => today()->addDays(5)]);

    $this->actingAs($user)
        ->get(route('leads.board', ['current_team' => $team->slug, 'follow_up' => 'due']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.count', 1)
            ->where('columns.0.leads.0.name', 'Due Lead'));
});

test('the board rejects column limits that are too large', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('leads.board', ['current_team' => $user->currentTeam->slug, 'limits' => ['new' => 5000]]))
        ->assertSessionHasErrors('limits.new');
});

test('moving a lead between stages is recorded on its timeline', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patch(route('leads.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'status' => LeadStatus::Interested->value,
        ])
        ->assertRedirect();

    $activity = $lead->activities()->first();

    expect($lead->fresh()->status)->toBe(LeadStatus::Interested)
        ->and($activity->type)->toBe(LeadActivityType::StatusChanged)
        ->and($activity->data)->toMatchArray(['from' => 'new', 'to' => 'interested']);
});

test('a follow-up date can be scheduled and cleared', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $url = route('leads.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]);

    $this->actingAs($user)->patch($url, ['next_follow_up_at' => '2026-11-01'])->assertRedirect();

    expect($lead->fresh()->next_follow_up_at->toDateString())->toBe('2026-11-01')
        ->and($lead->activities()->where('type', LeadActivityType::FollowUpScheduled)->count())->toBe(1);

    $this->actingAs($user)->patch($url, ['next_follow_up_at' => null])->assertRedirect();

    expect($lead->fresh()->next_follow_up_at)->toBeNull();
});

test('leads can be filtered by tier and by follow-ups that are due', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $hot = Lead::factory()->for($team)->create(['name' => 'Hot Lead', 'website' => null, 'reviews_count' => 250, 'rating' => 4.9, 'email' => 'a@example.com']);
    Lead::factory()->for($team)->withWebsite()->create(['name' => 'Cold Lead', 'reviews_count' => 0, 'rating' => null, 'phone' => null, 'category' => null, 'city' => null, 'website_audit' => ['score' => 100, 'error' => null, 'checks' => []]]);
    $due = Lead::factory()->for($team)->create(['name' => 'Due Lead', 'next_follow_up_at' => today()->subDay()]);
    Lead::factory()->for($team)->create(['name' => 'Done Lead', 'status' => LeadStatus::Won, 'next_follow_up_at' => today()->subDay()]);

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $team->slug, 'tier' => 'cold']))
        ->assertInertia(fn (Assert $page) => $page->has('leads.data', 1)->where('leads.data.0.name', 'Cold Lead'));

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $team->slug, 'follow_up' => 'due']))
        ->assertInertia(fn (Assert $page) => $page->has('leads.data', 1)->where('leads.data.0.name', 'Due Lead'));

    $this->actingAs($user)
        ->get(route('leads.index', ['current_team' => $team->slug, 'sort' => 'score']))
        ->assertInertia(fn (Assert $page) => $page->where('leads.data.0.id', $hot->id));
});

test('the leads csv export respects filters, team ownership and neutralises formulas', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    Lead::factory()->for($team)->create(['name' => '=HYPERLINK("http://evil.test")', 'status' => LeadStatus::Won]);
    Lead::factory()->for($team)->create(['name' => 'Plain Plumbing', 'status' => LeadStatus::New]);
    Lead::factory()->create(['name' => 'Other Team Plumbing', 'status' => LeadStatus::Won]);

    $csv = $this->actingAs($user)
        ->get(route('leads.export', ['current_team' => $team->slug, 'status' => 'won']))
        ->assertOk()
        ->assertDownload()
        ->streamedContent();

    expect($csv)
        ->toContain("'=HYPERLINK")
        ->not->toContain('Plain Plumbing')
        ->not->toContain('Other Team Plumbing');
});

test('an email can be sent to a lead and the pipeline follows up automatically', function () {
    Mail::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => 'owner@example.com', 'status' => LeadStatus::SiteGenerated]);

    $this->actingAs($user)
        ->post(route('leads.outreach.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'mode' => 'send',
            'subject' => 'Your free website',
            'body' => 'Hello there',
        ])
        ->assertRedirect();

    Mail::assertQueued(OutreachEmail::class, fn (OutreachEmail $mail) => $mail->hasTo('owner@example.com')
        && $mail->hasReplyTo($user->email)
        && $mail->emailSubject === 'Your free website');

    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Contacted)
        ->and($lead->last_contacted_at)->not->toBeNull()
        ->and($lead->next_follow_up_at->toDateString())->toBe(today()->addDays(3)->toDateString())
        ->and($lead->activities()->where('type', LeadActivityType::EmailSent)->exists())->toBeTrue();
});

test('sending requires an email address but contact can still be logged', function () {
    Mail::fake();

    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => null]);
    $url = route('leads.outreach.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]);

    $this->actingAs($user)
        ->post($url, ['mode' => 'send', 'subject' => 'Hi', 'body' => 'Hello'])
        ->assertSessionHasErrors('mode');

    $this->actingAs($user)
        ->post($url, ['mode' => 'log', 'subject' => 'Phoned them', 'body' => 'Spoke to the owner'])
        ->assertRedirect();

    Mail::assertNothingQueued();

    expect($lead->fresh()->status)->toBe(LeadStatus::Contacted)
        ->and($lead->activities()->where('type', LeadActivityType::MarkedContacted)->exists())->toBeTrue();
});

test('notes are added to the lead timeline', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('leads.notes.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), ['note' => 'Prefers calls in the morning'])
        ->assertRedirect();

    expect($lead->activities()->first())
        ->type->toBe(LeadActivityType::Note)
        ->summary->toBe('Prefers calls in the morning')
        ->user_id->toBe($user->id);
});

test("another team's leads cannot be touched", function () {
    $user = User::factory()->create();
    $foreign = Lead::factory()->create();

    $this->actingAs($user)
        ->post(route('leads.notes.store', ['current_team' => $user->currentTeam->slug, 'lead' => $foreign->id]), ['note' => 'Nope'])
        ->assertNotFound();
});

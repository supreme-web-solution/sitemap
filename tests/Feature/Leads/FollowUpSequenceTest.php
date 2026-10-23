<?php

use App\Enums\EnrollmentStatus;
use App\Enums\LeadStatus;
use App\Enums\LostReason;
use App\Mail\OutreachEmail;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('visiting sequences creates a starter sequence once', function () {
    $user = User::factory()->create();
    $url = route('sequences.index', ['current_team' => $user->currentTeam->slug]);

    $this->actingAs($user)->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sequences/Index')
            ->has('sequences', 1)
            ->has('mergeFields'));

    $this->actingAs($user)->get($url);

    expect($user->currentTeam->followUpSequences()->count())->toBe(1)
        ->and($user->currentTeam->followUpSequences()->sole()->steps()->count())->toBe(4);
});

test('a team can enroll a lead in a sequence and stop it', function () {
    Mail::fake();

    $user = User::factory()->create();
    $sequence = FollowUpSequence::factory()->for($user->currentTeam)->withSteps(2)->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => 'owner@example.com']);

    $this->actingAs($user)
        ->post(route('leads.enrollments.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'sequence_id' => $sequence->id,
        ])
        ->assertRedirect();

    $enrollment = $lead->enrollments()->sole();

    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->next_step_at)->not->toBeNull();

    $this->artisan('sequences:run')->assertSuccessful();

    Mail::assertQueued(OutreachEmail::class, fn (OutreachEmail $mail) => $mail->hasTo('owner@example.com'));

    $this->actingAs($user)
        ->delete(route('leads.enrollments.destroy', [
            'current_team' => $user->currentTeam->slug,
            'lead' => $lead->id,
            'enrollment' => $enrollment->id,
        ]))
        ->assertRedirect();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Stopped);
});

test('won and lost leads are not enrolled and active enrollments stop when the deal closes', function () {
    $user = User::factory()->create();
    $sequence = FollowUpSequence::factory()->for($user->currentTeam)->withSteps()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => 'owner@example.com']);

    $this->actingAs($user)
        ->post(route('leads.enrollments.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'sequence_id' => $sequence->id,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('leads.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'status' => LeadStatus::Won->value,
        ])
        ->assertRedirect();

    expect($lead->enrollments()->sole()->status)->toBe(EnrollmentStatus::Stopped);

    $lost = Lead::factory()->for($user->currentTeam)->create(['email' => 'lost@example.com', 'status' => LeadStatus::Lost]);

    $this->actingAs($user)
        ->post(route('leads.enrollments.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lost->id]), [
            'sequence_id' => $sequence->id,
        ])
        ->assertSessionHasErrors('sequence_id');
});

test('marking a lead lost requires a reason', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patch(route('leads.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'status' => LeadStatus::Lost->value,
        ])
        ->assertSessionHasErrors('lost_reason');

    $this->actingAs($user)
        ->patch(route('leads.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'status' => LeadStatus::Lost->value,
            'lost_reason' => LostReason::TooExpensive->value,
        ])
        ->assertRedirect();

    expect($lead->fresh()->lost_reason)->toBe(LostReason::TooExpensive);
});

test('a lead can be assigned to a teammate and given a task', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patch(route('leads.update', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'assigned_user_id' => $user->id,
        ])
        ->assertRedirect();

    expect($lead->fresh()->assigned_user_id)->toBe($user->id);

    $this->actingAs($user)
        ->post(route('leads.tasks.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'title' => 'Call the owner',
            'due_on' => today()->toDateString(),
        ])
        ->assertRedirect();

    $task = LeadTask::sole();

    expect($task->title)->toBe('Call the owner');

    $this->actingAs($user)
        ->patch(route('leads.tasks.update', [
            'current_team' => $user->currentTeam->slug,
            'lead' => $lead->id,
            'task' => $task->id,
        ]))
        ->assertRedirect();

    expect($task->fresh()->completed_at)->not->toBeNull();
});

test("another team's sequence cannot be used", function () {
    $user = User::factory()->create();
    $sequence = FollowUpSequence::factory()->withSteps()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => 'a@example.com']);

    $this->actingAs($user)
        ->post(route('leads.enrollments.store', ['current_team' => $user->currentTeam->slug, 'lead' => $lead->id]), [
            'sequence_id' => $sequence->id,
        ])
        ->assertSessionHasErrors('sequence_id');
});

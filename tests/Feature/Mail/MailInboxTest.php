<?php

use App\Enums\EnrollmentStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\MailConnectionProvider;
use App\Enums\MailConnectionStatus;
use App\Jobs\SyncTeamMailbox;
use App\Models\EmailThread;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\MailConnection;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Services\Mail\ImapMailConnectionClient;
use App\Services\Mail\InboundMailMessage;
use App\Services\Mail\InboundMailProcessor;
use App\Services\Mail\MailConnectionManager;
use App\Services\Mail\OutboundThreadRecorder;
use App\Services\Mail\StubImapMailConnectionClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeImapMailConnectionClient;

beforeEach(function () {
    $this->withoutVite();
});

function bindFakeImapClient(array $messages = []): FakeImapMailConnectionClient
{
    $fake = new FakeImapMailConnectionClient($messages);
    app()->instance(StubImapMailConnectionClient::class, $fake);
    app()->instance(ImapMailConnectionClient::class, $fake);

    return $fake;
}

test('an owner saves inbox credentials encrypted and without returning secrets to the browser', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)->put(route('mail-inbox.store', ['current_team' => $team->slug]), [
        'provider' => MailConnectionProvider::Imap->value,
        'email_address' => 'agency@example.com',
        'imap_host' => 'imap.example.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'agency@example.com',
        'imap_password' => 'imap-secret',
    ])->assertRedirect();

    $connection = $team->refresh()->mailConnection;

    expect($connection)->not->toBeNull()
        ->and($connection->email_address)->toBe('agency@example.com')
        ->and($connection->credentials['password'])->toBe('imap-secret')
        ->and(DB::table('mail_connections')->value('credentials'))->not->toContain('imap-secret');

    $this->actingAs($user)->get(route('mail-inbox.index', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('connected', true)
            ->missing('connection.imapPassword')
        );
});

test('logging an inbound reply manually records timeline activity and moves contacted leads to interested', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create([
        'email' => 'owner@biz.com',
        'status' => LeadStatus::Contacted,
    ]);

    $this->actingAs($user)->post(route('leads.inbound-email.store', [
        'current_team' => $user->currentTeam->slug,
        'lead' => $lead,
    ]), [
        'subject' => 'Re: Your new website preview',
        'snippet' => 'Looks great — can we talk Monday?',
    ])->assertRedirect();

    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Interested)
        ->and($lead->last_email_reply_at)->not->toBeNull()
        ->and($lead->activities()->where('type', LeadActivityType::EmailReceived)->exists())->toBeTrue();
});

test('sync imports a reply matched to a lead and links it to the outbound thread', function () {
    config(['inbox.imap_sync_enabled' => true]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $lead = Lead::factory()->for($team)->create([
        'email' => 'owner@biz.com',
        'status' => LeadStatus::Contacted,
    ]);

    $recorder = app(OutboundThreadRecorder::class);
    $outboundId = $recorder->generateMessageId();
    $thread = $recorder->record($lead, 'Your new website preview', $outboundId);

    $connection = MailConnection::factory()->for($team)->create([
        'email_address' => 'agency@example.com',
        'status' => MailConnectionStatus::Connected,
    ]);

    $inbound = new InboundMailMessage(
        externalMessageId: '<reply-1@biz.com>',
        fromAddress: 'owner@biz.com',
        toAddress: 'agency@example.com',
        subject: 'Re: Your new website preview',
        snippet: 'Yes, let us schedule a call.',
        receivedAt: Carbon::parse('2026-10-24 10:00:00'),
        inReplyTo: $outboundId,
    );

    bindFakeImapClient([$inbound]);

    (new SyncTeamMailbox($connection))->handle(app(MailConnectionManager::class), app(InboundMailProcessor::class));

    $lead->refresh();
    $thread->refresh();
    $connection->refresh();

    expect($lead->status)->toBe(LeadStatus::Interested)
        ->and($thread->replied_at)->not->toBeNull()
        ->and($connection->status)->toBe(MailConnectionStatus::Connected)
        ->and($connection->inboxMessages()->count())->toBe(1)
        ->and($lead->activities()->where('type', LeadActivityType::EmailReceived)->exists())->toBeTrue();
});

test('connecting an inbox queues sync when imap polling is enabled', function () {
    Queue::fake();
    config(['inbox.imap_sync_enabled' => true]);

    bindFakeImapClient();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)->put(route('mail-inbox.store', ['current_team' => $team->slug]), [
        'provider' => MailConnectionProvider::Imap->value,
        'email_address' => 'agency@example.com',
        'imap_host' => 'imap.example.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'agency@example.com',
        'imap_password' => 'imap-secret',
    ])->assertRedirect();

    Queue::assertPushed(SyncTeamMailbox::class);
});

test('an imported reply pauses active follow-up sequences for the lead', function () {
    config(['inbox.imap_sync_enabled' => true]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $lead = Lead::factory()->for($team)->create(['email' => 'owner@biz.com']);

    $sequence = FollowUpSequence::factory()->for($team)->create();
    $enrollment = SequenceEnrollment::query()->create([
        'team_id' => $team->id,
        'lead_id' => $lead->id,
        'follow_up_sequence_id' => $sequence->id,
        'status' => EnrollmentStatus::Active,
        'next_position' => 1,
        'next_step_at' => now()->addDay(),
    ]);

    $connection = MailConnection::factory()->for($team)->create();

    $inbound = new InboundMailMessage(
        externalMessageId: '<reply-seq@biz.com>',
        fromAddress: 'owner@biz.com',
        toAddress: 'agency@example.com',
        subject: 'Re: Hello',
        snippet: 'Stop emailing me — let us talk.',
        receivedAt: Carbon::now(),
    );

    bindFakeImapClient([$inbound]);

    (new SyncTeamMailbox($connection))->handle(app(MailConnectionManager::class), app(InboundMailProcessor::class));

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Stopped);
});

test('inbox sync does not attach another teams lead to imported messages', function () {
    config(['inbox.imap_sync_enabled' => true]);

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $foreignLead = Lead::factory()->create(['email' => 'owner@biz.com']);

    $connection = MailConnection::factory()->for($team)->create();

    $inbound = new InboundMailMessage(
        externalMessageId: '<cross-team@biz.com>',
        fromAddress: 'owner@biz.com',
        toAddress: 'agency@example.com',
        subject: 'Hello',
        snippet: 'Interested',
        receivedAt: Carbon::now(),
    );

    bindFakeImapClient([$inbound]);

    (new SyncTeamMailbox($connection))->handle(app(MailConnectionManager::class), app(InboundMailProcessor::class));

    $message = $connection->fresh()->inboxMessages()->first();

    expect($message)->not->toBeNull()
        ->and($message->lead_id)->toBeNull()
        ->and($foreignLead->activities()->where('type', LeadActivityType::EmailReceived)->exists())->toBeFalse();
});

test('private imap hosts are rejected when sync is enabled', function () {
    config(['inbox.imap_sync_enabled' => true]);

    bindFakeImapClient();

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)->put(route('mail-inbox.store', ['current_team' => $team->slug]), [
        'provider' => MailConnectionProvider::Imap->value,
        'email_address' => 'agency@example.com',
        'imap_host' => '127.0.0.1',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'agency@example.com',
        'imap_password' => 'imap-secret',
    ])->assertSessionHasErrors('imap_host');
});

test('outbound outreach registers a thread for reply matching', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => 'owner@biz.com']);

    Mail::fake();

    $this->actingAs($user)->post(route('leads.outreach.store', [
        'current_team' => $user->currentTeam->slug,
        'lead' => $lead,
    ]), [
        'mode' => 'send',
        'subject' => 'Quick intro',
        'body' => 'Hello there',
    ])->assertRedirect();

    expect(EmailThread::query()->where('lead_id', $lead->id)->exists())->toBeTrue();
});

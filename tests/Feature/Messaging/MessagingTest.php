<?php

use App\Enums\MessageStatus;
use App\Jobs\ProcessZernioEvent;
use App\Jobs\SendLeadMessage;
use App\Models\Lead;
use App\Models\Message;
use App\Models\MessagingAccount;
use App\Models\Plan;
use App\Models\User;
use App\Services\Messaging\MessagingException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function messagingSetup(array $accountAttributes = [], array $leadAttributes = []): array
{
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $account = new MessagingAccount;
    $account->forceFill([
        'team_id' => $team->id,
        'api_key' => 'zk_test',
        'webhook_secret' => 'whsec_test',
        'webhook_key' => 'hook-key-'.$team->id,
        'sms_from' => '+15550001111',
        'whatsapp_account_id' => 'wa_1',
        'timezone' => 'America/New_York',
        'quiet_start_hour' => 21,
        'quiet_end_hour' => 8,
        ...$accountAttributes,
    ])->save();

    $lead = Lead::factory()->for($team)->create([
        'phone' => '(555) 123-4567',
        'messaging_consent_at' => now(),
        ...$leadAttributes,
    ]);

    return [$user, $team, $lead, $account];
}

function sendArgs($team, $lead): array
{
    return ['current_team' => $team->slug, 'lead' => $lead];
}

function signedWebhook($test, MessagingAccount $account, array $payload, ?string $eventId = null)
{
    $body = json_encode($payload);

    return $test->call('POST', route('webhooks.zernio', $account->webhook_key), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ZERNIO_SIGNATURE' => hash_hmac('sha256', $body, $account->webhook_secret),
        ...($eventId ? ['HTTP_X_ZERNIO_EVENT_ID' => $eventId] : []),
    ], $body);
}

test('lead phone numbers are normalised to e164 when saved', function () {
    $lead = Lead::factory()->create(['phone' => '(555) 123-4567']);

    expect($lead->phone_e164)->toBe('+15551234567');

    $lead->update(['phone' => '+44 20 7946 0958']);
    expect($lead->refresh()->phone_e164)->toBe('+442079460958');

    $lead->update(['phone' => null]);
    expect($lead->refresh()->phone_e164)->toBeNull();
});

test('an owner connects messaging after the key checks out and the key is stored encrypted', function () {
    Http::fake(['zernio.com/*' => Http::response(['accounts' => []])]);
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)->put(route('messaging.store', $team->slug), [
        'api_key' => 'zk_live_abc',
        'sms_from' => '+15550001111',
        'timezone' => 'America/Chicago',
        'quiet_start_hour' => 20,
        'quiet_end_hour' => 9,
    ])->assertRedirect();

    $account = $team->refresh()->messagingAccount;

    expect($account->api_key)->toBe('zk_live_abc')
        ->and($account->webhook_key)->toHaveLength(32)
        ->and(DB::table('messaging_accounts')->value('api_key'))->not->toContain('zk_live_abc');
});

test('a rejected key is not saved', function () {
    Http::fake(['zernio.com/*' => Http::response(['error' => 'nope'], 401)]);
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('messaging.store', $user->currentTeam->slug), [
        'api_key' => 'bad',
        'timezone' => 'America/Chicago',
        'quiet_start_hour' => 20,
        'quiet_end_hour' => 9,
    ])->assertSessionHasErrors('api_key');

    expect($user->currentTeam->refresh()->messagingAccount)->toBeNull();
});

test('a message is queued on the messaging queue and sent with an idempotency key', function () {
    Carbon::setTestNow('2026-06-10 15:00:00');
    Http::fake(['zernio.com/*' => Http::response(['id' => 'msg_1', 'conversationId' => 'conv_1'])]);
    [$user, $team, $lead] = messagingSetup();

    $this->actingAs($user)->post(route('leads.messages.store', sendArgs($team, $lead)), [
        'channel' => 'sms',
        'body' => 'Hi, your new website is ready.',
    ])->assertRedirect();

    $message = Message::first();

    expect($message->status)->toBe(MessageStatus::Sent)
        ->and($message->external_id)->toBe('msg_1')
        ->and($message->phone)->toBe('+15551234567');

    Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', 'message-'.$message->id)
        && $request['to'] === '+15551234567'
        && $request['from'] === '+15550001111');

    Carbon::setTestNow();
});

test('messages are dispatched to the messaging queue', function () {
    Queue::fake();
    [$user, $team, $lead] = messagingSetup();

    $this->actingAs($user)->post(route('leads.messages.store', sendArgs($team, $lead)), ['channel' => 'sms', 'body' => 'Hello'])->assertRedirect();

    Queue::assertPushedOn('messaging', SendLeadMessage::class);
});

test('messages written during quiet hours wait until they end', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-06-10 23:30:00', 'America/New_York'));
    [$user, $team, $lead] = messagingSetup();

    $this->actingAs($user)->post(route('leads.messages.store', sendArgs($team, $lead)), ['channel' => 'sms', 'body' => 'Late hello'])->assertRedirect();

    $message = Message::first();

    expect($message->status)->toBe(MessageStatus::Queued)
        ->and($message->send_after->setTimezone('America/New_York')->format('Y-m-d H:i'))->toBe('2026-06-11 08:00');

    Carbon::setTestNow();
});

test('nobody can be messaged without consent or after opting out', function () {
    Queue::fake();
    [$user, $team, $lead] = messagingSetup(leadAttributes: ['messaging_consent_at' => null]);

    $this->actingAs($user)->post(route('leads.messages.store', sendArgs($team, $lead)), ['channel' => 'sms', 'body' => 'Hi'])
        ->assertSessionHasErrors('channel');

    $lead->forceFill(['messaging_consent_at' => now(), 'messaging_opted_out_at' => now()])->save();

    $this->actingAs($user)->post(route('leads.messages.store', sendArgs($team, $lead)), ['channel' => 'sms', 'body' => 'Hi'])
        ->assertSessionHasErrors('channel');

    expect(Message::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a number that opted out at the provider is blocked for good', function () {
    Carbon::setTestNow('2026-06-10 15:00:00');
    Http::fake(['zernio.com/*' => Http::response(['error' => 'Recipient has opted out'], 409)]);
    [$user, $team, $lead] = messagingSetup();

    $this->actingAs($user)->post(route('leads.messages.store', sendArgs($team, $lead)), ['channel' => 'sms', 'body' => 'Hi']);

    expect(Message::first()->status)->toBe(MessageStatus::Failed)
        ->and($lead->refresh()->messaging_opted_out_at)->not->toBeNull();

    Carbon::setTestNow();
});

test('a server error keeps the message queued so the job can retry', function () {
    Carbon::setTestNow('2026-06-10 15:00:00');
    Http::fake(['zernio.com/*' => Http::response(['error' => 'down'], 503)]);
    [$user, $team, $lead] = messagingSetup();

    $message = Message::create([
        'team_id' => $team->id, 'lead_id' => $lead->id, 'channel' => 'sms', 'direction' => 'out',
        'status' => 'queued', 'body' => 'Hi', 'phone' => '+15551234567',
    ]);

    expect(fn () => (new SendLeadMessage($message))->handle())->toThrow(MessagingException::class)
        ->and($message->refresh()->status)->toBe(MessageStatus::Queued);

    Carbon::setTestNow();
});

test('the monthly message allowance is enforced', function () {
    Queue::fake();
    [$user, $team, $lead] = messagingSetup();
    $team->forceFill(['plan_id' => Plan::factory()->withAllFeatures()->limited(['messages' => 1])->create()->id])->save();

    $url = route('leads.messages.store', sendArgs($team, $lead));
    $this->actingAs($user)->post($url, ['channel' => 'sms', 'body' => 'One']);
    $this->actingAs($user)->post($url, ['channel' => 'sms', 'body' => 'Two']);

    expect(Message::count())->toBe(1);
});

test('messaging is blocked when the plan leaves it out', function () {
    Queue::fake();
    [$user, $team, $lead] = messagingSetup();
    $team->forceFill(['plan_id' => Plan::factory()->create(['features' => []])->id])->save();

    $this->actingAs($user)->post(route('leads.messages.store', sendArgs($team, $lead)), ['channel' => 'sms', 'body' => 'Hi']);

    expect(Message::count())->toBe(0);
});

test('webhooks need a valid signature', function () {
    Queue::fake();
    [, , , $account] = messagingSetup();

    $this->postJson(route('webhooks.zernio', $account->webhook_key), ['event' => 'message.received'], ['X-Zernio-Signature' => 'wrong'])
        ->assertStatus(400);

    Queue::assertNothingPushed();
});

test('a reply is stored, and STOP opts the contact out', function () {
    [, , $lead, $account] = messagingSetup();

    signedWebhook($this, $account, ['event' => 'message.received', 'data' => ['from' => '+15551234567', 'text' => 'Sounds good', 'id' => 'in_1', 'platform' => 'sms']])->assertOk();
    signedWebhook($this, $account, ['event' => 'message.received', 'data' => ['from' => '+15551234567', 'text' => 'STOP', 'id' => 'in_2', 'platform' => 'sms']])->assertOk();

    expect(Message::where('direction', 'in')->count())->toBe(2)
        ->and($lead->refresh()->messaging_opted_out_at)->not->toBeNull();

    signedWebhook($this, $account, ['event' => 'message.received', 'data' => ['from' => '+15551234567', 'text' => 'START', 'id' => 'in_3', 'platform' => 'sms']]);

    expect($lead->refresh()->messaging_opted_out_at)->toBeNull();
});

test('delivery updates change the message status and duplicates are ignored', function () {
    Queue::fake();
    [, $team, $lead, $account] = messagingSetup();
    $message = Message::create([
        'team_id' => $team->id, 'lead_id' => $lead->id, 'channel' => 'sms', 'direction' => 'out',
        'status' => 'sent', 'body' => 'Hi', 'phone' => '+15551234567', 'external_id' => 'msg_9',
    ]);

    $payload = ['event' => 'message.delivered', 'data' => ['messageId' => 'msg_9']];

    signedWebhook($this, $account, $payload, 'evt-1')->assertOk();
    signedWebhook($this, $account, $payload, 'evt-1')->assertSee('duplicate');

    Queue::assertPushed(ProcessZernioEvent::class, 1);

    (new ProcessZernioEvent($team->id, $payload))->handle();
    expect($message->refresh()->status)->toBe(MessageStatus::Delivered);
});

test('members cannot change messaging settings', function () {
    [$owner, $team] = messagingSetup();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => 'member']);

    $this->actingAs($member)->put(route('messaging.store', $team->slug), [
        'timezone' => 'America/Chicago', 'quiet_start_hour' => 20, 'quiet_end_hour' => 9,
    ])->assertForbidden();
});

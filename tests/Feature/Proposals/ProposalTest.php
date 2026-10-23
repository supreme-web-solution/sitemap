<?php

use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\ProposalStatus;
use App\Mail\InvoiceEmail;
use App\Mail\ProposalEmail;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Proposal;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function proposalPayload(Lead $lead, array $overrides = []): array
{
    return [
        'lead_id' => $lead->id,
        'title' => 'Website and hosting',
        'intro' => 'Thanks for your time.',
        'items' => [
            ['description' => 'Website build', 'quantity' => 1, 'unit_price' => 1500, 'interval' => null],
            ['description' => 'Hosting', 'quantity' => 1, 'unit_price' => 50, 'interval' => 'monthly'],
        ],
        ...$overrides,
    ];
}

function sentProposal(?Lead $lead = null): Proposal
{
    $lead ??= Lead::factory()->create(['email' => 'client@example.com']);

    return Proposal::factory()->withDefaultItems()->create(['lead_id' => $lead->id])->load('items');
}

test('a draft proposal is saved with numbered items', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)->post(route('proposals.store', ['current_team' => $user->currentTeam->slug]), proposalPayload($lead))->assertSessionHasNoErrors();

    $proposal = Proposal::sole();

    expect($proposal->number)->toBe('PROP-0001')
        ->and($proposal->status)->toBe(ProposalStatus::Draft)
        ->and($proposal->items)->toHaveCount(2)
        ->and($proposal->oneTimeCents())->toBe(150000)
        ->and($proposal->recurringCents()->all())->toBe(['monthly' => 5000])
        ->and($proposal->firstYearCents())->toBe(150000 + 60000);
});

test('a proposal cannot be written for another team\'s lead or without items', function () {
    $user = User::factory()->create();
    $foreign = Lead::factory()->create();

    $this->actingAs($user)->post(route('proposals.store', ['current_team' => $user->currentTeam->slug]), proposalPayload($foreign))->assertSessionHasErrors('lead_id');
    $this->actingAs($user)->post(route('proposals.store', ['current_team' => $user->currentTeam->slug]), ['lead_id' => Lead::factory()->for($user->currentTeam)->create()->id, 'title' => 'X', 'items' => []])->assertSessionHasErrors('items');
});

test('editing replaces the items, but an accepted proposal is locked', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $proposal = Proposal::factory()->withDefaultItems()->create(['lead_id' => $lead->id, 'status' => ProposalStatus::Draft]);
    $url = route('proposals.update', ['current_team' => $user->currentTeam->slug, 'proposal' => $proposal->id]);

    $this->actingAs($user)->put($url, proposalPayload($lead, ['items' => [['description' => 'Only item', 'quantity' => 2, 'unit_price' => 10]]]))->assertSessionHasNoErrors();
    expect($proposal->items()->count())->toBe(1);

    $proposal->forceFill(['status' => ProposalStatus::Accepted])->save();
    $this->actingAs($user)->put($url, proposalPayload($lead))->assertForbidden();
});

test('only drafts can be deleted and other teams cannot see a proposal', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $draft = Proposal::factory()->create(['lead_id' => $lead->id, 'status' => ProposalStatus::Draft]);
    $sent = Proposal::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)->delete(route('proposals.destroy', ['current_team' => $user->currentTeam->slug, 'proposal' => $sent->id]))->assertForbidden();
    $this->actingAs($user)->delete(route('proposals.destroy', ['current_team' => $user->currentTeam->slug, 'proposal' => $draft->id]))->assertRedirect();
    expect(Proposal::whereKey($draft->id)->exists())->toBeFalse();

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('proposals.edit', ['current_team' => $user->currentTeam->slug, 'proposal' => $sent->id]))->assertForbidden();
});

test('sending a proposal emails the client and logs it', function () {
    Mail::fake();
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => 'client@example.com']);
    $proposal = Proposal::factory()->withDefaultItems()->create(['lead_id' => $lead->id, 'status' => ProposalStatus::Draft, 'sent_at' => null]);

    $this->actingAs($user)->post(route('proposals.send', ['current_team' => $user->currentTeam->slug, 'proposal' => $proposal->id]), ['email' => true])->assertSessionHasNoErrors();

    Mail::assertQueued(ProposalEmail::class, fn ($mail) => $mail->hasTo('client@example.com'));
    expect($proposal->refresh()->status)->toBe(ProposalStatus::Sent)
        ->and($lead->activities()->where('type', LeadActivityType::ProposalSent)->exists())->toBeTrue();
});

test('emailing needs an email address on the lead', function () {
    Mail::fake();
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => null]);
    $proposal = Proposal::factory()->withDefaultItems()->create(['lead_id' => $lead->id, 'status' => ProposalStatus::Draft]);

    $this->actingAs($user)->post(route('proposals.send', ['current_team' => $user->currentTeam->slug, 'proposal' => $proposal->id]), ['email' => true])->assertSessionHasErrors('proposal');

    Mail::assertNothingQueued();
});

test('the public page hides drafts and records the first view', function () {
    $draft = Proposal::factory()->create(['status' => ProposalStatus::Draft]);
    $this->get($draft->publicUrl())->assertNotFound();

    $proposal = sentProposal();
    $this->get($proposal->publicUrl())->assertOk()->assertSee('Website design and build')->assertSee('$1,500.00')->assertSee('Every month');

    expect($proposal->refresh()->viewed_at)->not->toBeNull();
});

test('accepting bills the one-time items, starts recurring billing and wins the lead', function () {
    Mail::fake();
    $proposal = sentProposal();

    $response = $this->post(route('proposals.public.accept', $proposal->public_token), ['name' => 'Pat Client', 'agree' => '1']);

    $oneTime = Invoice::where('total_cents', 150000)->sole();
    $response->assertRedirect($oneTime->payUrl());

    $schedule = RecurringInvoice::sole();

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Accepted)
        ->and($proposal->accepted_name)->toBe('Pat Client')
        ->and($proposal->invoice_id)->toBe($oneTime->id)
        ->and($oneTime->status)->toBe(InvoiceStatus::Open)
        ->and($oneTime->items)->toHaveCount(1)
        ->and($schedule->interval)->toBe(BillingInterval::Monthly)
        ->and($schedule->totalCents())->toBe(5000)
        ->and(Invoice::where('recurring_invoice_id', $schedule->id)->where('total_cents', 5000)->count())->toBe(1)
        ->and($proposal->lead->refresh()->status)->toBe(LeadStatus::Won)
        ->and($proposal->lead->deal_value)->toBe(2100)
        ->and($proposal->lead->activities()->where('type', LeadActivityType::ProposalAccepted)->exists())->toBeTrue();

    Mail::assertQueued(InvoiceEmail::class);
});

test('a recurring-only proposal sends the client to the first recurring invoice', function () {
    Mail::fake();
    $proposal = Proposal::factory()->create(['lead_id' => Lead::factory()->create()->id]);
    $proposal->items()->create(['description' => 'Retainer', 'quantity' => 1, 'unit_cents' => 20000, 'interval' => 'quarterly']);

    $response = $this->post(route('proposals.public.accept', $proposal->public_token), ['name' => 'Pat', 'agree' => '1']);

    $invoice = Invoice::sole();
    $response->assertRedirect($invoice->payUrl());
    expect($invoice->total_cents)->toBe(20000)->and(RecurringInvoice::sole()->interval)->toBe(BillingInterval::Quarterly);
});

test('accepting twice does not bill twice', function () {
    $proposal = sentProposal();
    $payload = ['name' => 'Pat Client', 'agree' => '1'];

    $this->post(route('proposals.public.accept', $proposal->public_token), $payload);
    $this->post(route('proposals.public.accept', $proposal->public_token), $payload);

    expect(Invoice::count())->toBe(2)->and(RecurringInvoice::count())->toBe(1);
});

test('accepting needs a name and the agreement', function () {
    $proposal = sentProposal();

    $this->post(route('proposals.public.accept', $proposal->public_token), ['name' => '', 'agree' => ''])->assertSessionHasErrors(['name', 'agree']);

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Sent)->and(Invoice::count())->toBe(0);
});

test('an expired proposal cannot be accepted', function () {
    $proposal = sentProposal();
    $proposal->forceFill(['valid_until' => today()->subDay()])->save();

    $this->post(route('proposals.public.accept', $proposal->public_token), ['name' => 'Pat', 'agree' => '1'])->assertSessionHas('notice');

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Sent)->and(Invoice::count())->toBe(0);
});

test('the client can decline, and the agency can reopen it', function () {
    $user = User::factory()->create();
    $proposal = sentProposal(Lead::factory()->for($user->currentTeam)->create(['email' => 'client@example.com']));

    $this->post(route('proposals.public.decline', $proposal->public_token))->assertRedirect();

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Declined)
        ->and($proposal->lead->activities()->where('type', LeadActivityType::ProposalDeclined)->exists())->toBeTrue();

    $this->actingAs($user)->post(route('proposals.send', ['current_team' => $proposal->team->slug, 'proposal' => $proposal->id]));

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Sent);
});

test('packages can be created, priced and used as one-time or recurring services', function () {
    $user = User::factory()->create();
    $slug = $user->currentTeam->slug;

    $this->actingAs($user)->post(route('packages.store', ['current_team' => $slug]), ['name' => 'Hosting', 'price' => 49.99, 'interval' => 'monthly'])->assertSessionHasNoErrors();

    $package = Package::sole();
    expect($package->unit_cents)->toBe(4999)->and($package->interval)->toBe(BillingInterval::Monthly);

    $this->actingAs($user)->put(route('packages.update', ['current_team' => $slug, 'package' => $package->id]), ['name' => 'Hosting', 'price' => 59, 'interval' => '', 'is_active' => false]);
    expect($package->refresh()->unit_cents)->toBe(5900)->and($package->interval)->toBeNull()->and($package->is_active)->toBeFalse();

    $this->actingAs(User::factory()->create())->delete(route('packages.destroy', ['current_team' => $slug, 'package' => $package->id]))->assertForbidden();

    $this->actingAs($user)->delete(route('packages.destroy', ['current_team' => $slug, 'package' => $package->id]));
    expect(Package::count())->toBe(0);
});
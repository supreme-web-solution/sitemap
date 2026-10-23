<?php

use App\Enums\InvoiceStatus;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Mail\InvoiceEmail;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Lead}
 */
function invoiceOwner(array $leadAttributes = []): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => 'client@example.com', ...$leadAttributes]);

    return [$user, $lead];
}

/**
 * @return array<string, mixed>
 */
function invoicePayload(Lead $lead, array $overrides = []): array
{
    return [
        'lead_id' => $lead->id,
        'due_on' => now()->addDays(14)->toDateString(),
        'notes' => 'Thanks for your business',
        'items' => [
            ['description' => 'Website design', 'quantity' => 1, 'unit_price' => '1200.50'],
            ['description' => 'Hosting', 'quantity' => 3, 'unit_price' => 25],
        ],
        ...$overrides,
    ];
}

test('an invoice is created as a numbered draft with the total in cents', function () {
    [$user, $lead] = invoiceOwner();

    $this->actingAs($user)
        ->post(route('invoices.store', ['current_team' => $user->currentTeam->slug]), invoicePayload($lead))
        ->assertRedirect();

    $invoice = Invoice::sole();

    expect($invoice->number)->toBe('INV-0001')
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->total_cents)->toBe(120050 + 7500)
        ->and($invoice->items)->toHaveCount(2)
        ->and($invoice->items->first()->unit_cents)->toBe(120050)
        ->and($invoice->public_token)->toHaveLength(40);

    $this->actingAs($user)->post(route('invoices.store', ['current_team' => $user->currentTeam->slug]), invoicePayload($lead));

    expect(Invoice::latest('id')->first()->number)->toBe('INV-0002');
});

test('an invoice cannot be made for another teams lead', function () {
    [$user] = invoiceOwner();
    $other = Lead::factory()->create();

    $this->actingAs($user)
        ->post(route('invoices.store', ['current_team' => $user->currentTeam->slug]), invoicePayload($other))
        ->assertSessionHasErrors('lead_id');

    expect(Invoice::count())->toBe(0);
});

test('invoice line items are validated', function (array $items) {
    [$user, $lead] = invoiceOwner();

    $this->actingAs($user)
        ->post(route('invoices.store', ['current_team' => $user->currentTeam->slug]), invoicePayload($lead, ['items' => $items]))
        ->assertSessionHasErrors('items');

    expect(Invoice::count())->toBe(0);
})->with([
    'total under fifty cents' => [[['description' => 'Tiny', 'quantity' => 1, 'unit_price' => 0.10]]],
    'no items' => [[]],
]);

test('only drafts can be edited or deleted', function () {
    [$user, $lead] = invoiceOwner();
    $team = $user->currentTeam;
    $draft = Invoice::factory()->draft()->create(['lead_id' => $lead->id]);
    $open = Invoice::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->put(route('invoices.update', ['current_team' => $team->slug, 'invoice' => $draft->id]), invoicePayload($lead, ['items' => [['description' => 'Only item', 'quantity' => 2, 'unit_price' => 10]]]))
        ->assertSessionHasNoErrors();

    expect($draft->fresh()->total_cents)->toBe(2000)->and($draft->items()->count())->toBe(1);

    $this->put(route('invoices.update', ['current_team' => $team->slug, 'invoice' => $open->id]), invoicePayload($lead))
        ->assertSessionHasErrors('invoice');
    $this->delete(route('invoices.destroy', ['current_team' => $team->slug, 'invoice' => $open->id]))
        ->assertSessionHasErrors('invoice');
    $this->delete(route('invoices.destroy', ['current_team' => $team->slug, 'invoice' => $draft->id]))
        ->assertRedirect();

    expect(Invoice::count())->toBe(1);
});

test('invoices are scoped to the team', function () {
    [$user] = invoiceOwner();
    $foreign = Invoice::factory()->create();

    $this->actingAs($user)
        ->get(route('invoices.edit', ['current_team' => $user->currentTeam->slug, 'invoice' => $foreign->id]))
        ->assertNotFound();

    $this->post(route('invoices.void', ['current_team' => $user->currentTeam->slug, 'invoice' => $foreign->id]))
        ->assertNotFound();
});

test('the invoice list shows stats for the team', function () {
    [$user, $lead] = invoiceOwner();
    Invoice::factory()->create(['lead_id' => $lead->id, 'total_cents' => 10000, 'due_on' => now()->subDay()]);
    Invoice::factory()->paid()->create(['lead_id' => $lead->id, 'total_cents' => 5000]);
    Invoice::factory()->create();

    $this->actingAs($user)
        ->get(route('invoices.index', ['current_team' => $user->currentTeam->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/Index')
            ->where('stats.outstandingCents', 10000)
            ->where('stats.overdueCount', 1)
            ->where('stats.paidThisMonthCents', 5000)
            ->has('invoices.data', 2));
});

test('sending an invoice opens it, emails the lead and records activity', function () {
    Mail::fake();
    [$user, $lead] = invoiceOwner();
    $invoice = Invoice::factory()->draft()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('invoices.send', ['current_team' => $user->currentTeam->slug, 'invoice' => $invoice->id]), ['email' => true])
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->fresh()->sent_at)->not->toBeNull()
        ->and($lead->activities()->where('type', LeadActivityType::InvoiceSent)->count())->toBe(1);

    Mail::assertQueued(InvoiceEmail::class, fn (InvoiceEmail $mail) => $mail->hasTo('client@example.com'));
});

test('an invoice can be issued without emailing and emailing needs an address', function () {
    Mail::fake();
    [$user, $lead] = invoiceOwner(['email' => null]);
    $invoice = Invoice::factory()->draft()->create(['lead_id' => $lead->id]);
    $parameters = ['current_team' => $user->currentTeam->slug, 'invoice' => $invoice->id];

    $this->actingAs($user)->post(route('invoices.send', $parameters), ['email' => true])
        ->assertSessionHasErrors('invoice');
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);

    $this->post(route('invoices.send', $parameters))->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
    Mail::assertNothingQueued();
});

test('marking an invoice paid by hand wins the lead', function () {
    [$user, $lead] = invoiceOwner(['status' => LeadStatus::Interested]);
    $invoice = Invoice::factory()->create(['lead_id' => $lead->id]);
    $parameters = ['current_team' => $user->currentTeam->slug, 'invoice' => $invoice->id];

    $this->actingAs($user)->post(route('invoices.paid', $parameters))->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_via)->toBe('manual')
        ->and($lead->fresh()->status)->toBe(LeadStatus::Won)
        ->and($lead->activities()->where('type', LeadActivityType::InvoicePaid)->count())->toBe(1);

    $this->post(route('invoices.paid', $parameters))->assertSessionHasErrors('invoice');
    $this->post(route('invoices.void', $parameters))->assertSessionHasErrors('invoice');
});

test('a sent invoice can be voided', function () {
    [$user, $lead] = invoiceOwner();
    $invoice = Invoice::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->post(route('invoices.void', ['current_team' => $user->currentTeam->slug, 'invoice' => $invoice->id]))
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void)->and($invoice->fresh()->isPayable())->toBeFalse();
});

test('guests cannot reach invoices', function () {
    $this->get('/some-team/invoices')->assertRedirect();
});

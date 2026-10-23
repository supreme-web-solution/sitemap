<?php

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\User;

test('a paid invoice can be refunded without calling a provider when it was marked paid by hand', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $invoice = Invoice::factory()->for($lead)->paid()->create();

    $this->actingAs($user)
        ->post(route('invoices.refund', ['current_team' => $user->currentTeam->slug, 'invoice' => $invoice->id]))
        ->assertRedirect();

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Refunded)
        ->refunded_at->not->toBeNull();
});

test('a draft invoice cannot be refunded', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();
    $invoice = Invoice::factory()->for($lead)->draft()->create();

    $this->actingAs($user)
        ->post(route('invoices.refund', ['current_team' => $user->currentTeam->slug, 'invoice' => $invoice->id]))
        ->assertSessionHasErrors('invoice');
});

test('an invoice can be saved as a deposit', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('invoices.store', ['current_team' => $user->currentTeam->slug]), [
            'lead_id' => $lead->id,
            'kind' => InvoiceKind::Deposit->value,
            'items' => [
                ['description' => 'Deposit', 'quantity' => 1, 'unit_price' => 500],
            ],
        ])
        ->assertRedirect();

    expect(Invoice::sole()->kind)->toBe(InvoiceKind::Deposit);
});

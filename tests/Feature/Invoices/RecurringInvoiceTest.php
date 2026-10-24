<?php

use App\Enums\BillingInterval;
use App\Models\Lead;
use App\Models\RecurringInvoice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Lead, 2: string}
 */
function recurringOwner(): array
{
    $user = User::factory()->create();
    $lead = Lead::factory()->for($user->currentTeam)->create(['email' => 'client@example.com']);

    return [$user, $lead, $user->currentTeam->slug];
}

/**
 * @return array<string, mixed>
 */
function recurringPayload(Lead $lead, array $overrides = []): array
{
    return [
        'lead_id' => $lead->id,
        'interval' => 'monthly',
        'starts_on' => today()->addDays(3)->toDateString(),
        'ends_on' => null,
        'due_in_days' => 14,
        'email_client' => true,
        'collection_mode' => 'invoice',
        'notes' => null,
        'items' => [['description' => 'Hosting and maintenance', 'quantity' => 1, 'unit_price' => '49.50']],
        ...$overrides,
    ];
}

test('a recurring schedule is created with its first run on the start date', function () {
    [$user, $lead, $team] = recurringOwner();

    $this->actingAs($user)
        ->post(route('recurring-invoices.store', ['current_team' => $team]), recurringPayload($lead))
        ->assertSessionHasNoErrors();

    $schedule = RecurringInvoice::sole();

    expect($schedule->interval)->toBe(BillingInterval::Monthly)
        ->and($schedule->next_run_on->toDateString())->toBe(today()->addDays(3)->toDateString())
        ->and($schedule->is_active)->toBeTrue()
        ->and($schedule->cycles_run)->toBe(0)
        ->and($schedule->items)->toBe([['description' => 'Hosting and maintenance', 'quantity' => 1, 'unit_cents' => 4950]])
        ->and($schedule->totalCents())->toBe(4950);
});

test('a schedule needs a valid client, interval, start date and total', function (string $field, array $override) {
    [$user, $lead, $team] = recurringOwner();

    $this->actingAs($user)
        ->post(route('recurring-invoices.store', ['current_team' => $team]), recurringPayload($lead, $override))
        ->assertSessionHasErrors($field);

    expect(RecurringInvoice::count())->toBe(0);
})->with([
    'unknown interval' => ['interval', ['interval' => 'weekly']],
    'start in the past' => ['starts_on', ['starts_on' => '2020-01-01']],
    'end before start' => ['ends_on', ['ends_on' => '2020-01-01']],
    'total under fifty cents' => ['items', ['items' => [['description' => 'Tiny', 'quantity' => 1, 'unit_price' => 0.1]]]],
]);

test('a schedule cannot bill another teams lead', function () {
    [$user, , $team] = recurringOwner();

    $this->actingAs($user)
        ->post(route('recurring-invoices.store', ['current_team' => $team]), recurringPayload(Lead::factory()->create()))
        ->assertSessionHasErrors('lead_id');
});

test('editing a schedule changes future invoices but not its client or dates', function () {
    [$user, $lead, $team] = recurringOwner();
    $schedule = RecurringInvoice::factory()->create(['lead_id' => $lead->id, 'starts_on' => today()->addWeek(), 'next_run_on' => today()->addWeek()]);
    $other = Lead::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->put(route('recurring-invoices.update', ['current_team' => $team, 'recurringInvoice' => $schedule->id]), [
            ...recurringPayload($other, ['starts_on' => '2030-01-01', 'interval' => 'yearly', 'due_in_days' => 30]),
            'items' => [['description' => 'New price', 'quantity' => 2, 'unit_price' => 100]],
        ])
        ->assertSessionHasNoErrors();

    $fresh = $schedule->fresh();

    expect($fresh->totalCents())->toBe(20000)
        ->and($fresh->due_in_days)->toBe(30)
        ->and($fresh->lead_id)->toBe($lead->id)
        ->and($fresh->interval)->toBe(BillingInterval::Monthly)
        ->and($fresh->starts_on->toDateString())->toBe(today()->addWeek()->toDateString());
});

test('a schedule can be paused and resumed, skipping the dates missed meanwhile', function () {
    [$user, $lead, $team] = recurringOwner();
    $schedule = RecurringInvoice::factory()->paused()->create([
        'lead_id' => $lead->id,
        'starts_on' => today()->subMonths(3)->subDays(5),
        'next_run_on' => today()->subMonths(2),
        'cycles_run' => 1,
    ]);
    $parameters = ['current_team' => $team, 'recurringInvoice' => $schedule->id];

    $this->actingAs($user)->post(route('recurring-invoices.toggle', $parameters))->assertSessionHasNoErrors();

    $fresh = $schedule->fresh();

    expect($fresh->is_active)->toBeTrue()
        ->and($fresh->next_run_on->isFuture())->toBeTrue()
        ->and($fresh->cycles_run)->toBeGreaterThan(1);

    $this->post(route('recurring-invoices.toggle', $parameters));

    expect($schedule->fresh()->is_active)->toBeFalse();
});

test('a finished schedule cannot be resumed', function () {
    [$user, $lead, $team] = recurringOwner();
    $schedule = RecurringInvoice::factory()->paused()->create(['lead_id' => $lead->id, 'next_run_on' => null]);

    $this->actingAs($user)
        ->post(route('recurring-invoices.toggle', ['current_team' => $team, 'recurringInvoice' => $schedule->id]))
        ->assertSessionHasErrors('schedule');
});

test('a schedule can be deleted and the list is scoped to the team', function () {
    [$user, $lead, $team] = recurringOwner();
    $schedule = RecurringInvoice::factory()->create(['lead_id' => $lead->id]);
    RecurringInvoice::factory()->create();

    $this->actingAs($user)
        ->get(route('recurring-invoices.index', ['current_team' => $team]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/Recurring')
            ->has('schedules', 1)
            ->where('schedules.0.status', 'active')
            ->where('schedules.0.total', '$50.00'));

    $this->delete(route('recurring-invoices.destroy', ['current_team' => $team, 'recurringInvoice' => $schedule->id]))->assertSessionHasNoErrors();

    expect(RecurringInvoice::count())->toBe(1);
});

test('another teams schedule cannot be touched', function () {
    [$user, , $team] = recurringOwner();
    $foreign = RecurringInvoice::factory()->create();

    $this->actingAs($user)
        ->delete(route('recurring-invoices.destroy', ['current_team' => $team, 'recurringInvoice' => $foreign->id]))
        ->assertNotFound();
});

<?php

use App\Actions\Invoices\MarkInvoicePaid;
use App\Actions\Sites\HandOffSiteFiles;
use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\TeamRole;
use App\Jobs\SendInvoicePaidEmails;
use App\Mail\InvoicePaidClientEmail;
use App\Mail\InvoicePaidOwnerEmail;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\RecurringInvoice;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('marking an invoice paid queues owner and client payment emails', function () {
    Mail::fake();
    Queue::fake([SendInvoicePaidEmails::class]);

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $lead = Lead::factory()->for($team)->create([
        'email' => 'client@example.com',
        'status' => LeadStatus::Interested,
    ]);
    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'lead_id' => $lead->id,
        'status' => InvoiceStatus::Open,
        'total_cents' => 19900,
    ]);

    app(MarkInvoicePaid::class)->handle($invoice, 'stripe', 'pi_test');

    Queue::assertPushed(SendInvoicePaidEmails::class, fn (SendInvoicePaidEmails $job) => $job->invoice->is($invoice));
});

test('paid invoice emails go to the owner and client with a website download when a site exists', function () {
    Mail::fake();

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $lead = Lead::factory()->for($team)->create([
        'name' => 'Handyman Ram',
        'email' => 'client@example.com',
        'status' => LeadStatus::Interested,
    ]);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'team_id' => $team->id,
        'published_at' => now(),
    ]);
    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'lead_id' => $lead->id,
        'status' => InvoiceStatus::Paid,
        'paid_at' => now(),
        'paid_via' => 'stripe',
        'total_cents' => 19900,
        'notes' => 'Website and hosting proposal',
    ]);

    (new SendInvoicePaidEmails($invoice))->handle(app(HandOffSiteFiles::class));

    Mail::assertQueued(InvoicePaidOwnerEmail::class, function (InvoicePaidOwnerEmail $mail) use ($owner, $invoice) {
        return $mail->hasTo($owner->email)
            && $mail->invoice->is($invoice);
    });

    $clientMail = null;

    Mail::assertQueued(InvoicePaidClientEmail::class, function (InvoicePaidClientEmail $mail) use (&$clientMail, $invoice) {
        $clientMail = $mail;

        return $mail->hasTo('client@example.com')
            && $mail->invoice->is($invoice)
            && filled($mail->downloadUrl);
    });

    expect($clientMail)->not->toBeNull()
        ->and($site->fresh()->published_at)->toBeNull()
        ->and($site->fresh()->transferred_to_email)->toBe('client@example.com');

    $clientMail->assertSeeInHtml('Your website is ready')
        ->assertSeeInHtml('Handyman Ram')
        ->assertSeeInHtml('$199.00')
        ->assertSeeInHtml('Download your website')
        ->assertSeeInText('No login needed');
});

test('recurring invoice payments email a receipt without transferring the site again', function () {
    Mail::fake();

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $lead = Lead::factory()->for($team)->create(['email' => 'client@example.com']);
    $site = Site::factory()->create([
        'lead_id' => $lead->id,
        'team_id' => $team->id,
        'published_at' => now(),
        'transferred_at' => null,
    ]);
    $schedule = RecurringInvoice::factory()->create(['lead_id' => $lead->id]);
    $invoice = Invoice::factory()->create([
        'team_id' => $team->id,
        'lead_id' => $lead->id,
        'status' => InvoiceStatus::Paid,
        'paid_at' => now(),
        'paid_via' => 'stripe',
        'total_cents' => 5000,
        'recurring_invoice_id' => $schedule->id,
    ]);

    (new SendInvoicePaidEmails($invoice))->handle(app(HandOffSiteFiles::class));

    Mail::assertQueued(InvoicePaidOwnerEmail::class);
    Mail::assertQueued(InvoicePaidClientEmail::class, fn (InvoicePaidClientEmail $mail) => $mail->downloadUrl === null);

    expect($site->fresh()->published_at)->not->toBeNull()
        ->and($site->fresh()->transferred_at)->toBeNull();
});

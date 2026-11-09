<?php

use App\Models\Lead;
use App\Models\User;
use App\Notifications\SiteContactNotification;
use Illuminate\Support\Facades\View;

test('the website message email uses the updated card layout', function () {
    $owner = User::factory()->create();
    $lead = Lead::factory()->for($owner->currentTeam)->create(['name' => 'Handyman Mike']);

    $mail = (new SiteContactNotification(
        lead: $lead,
        name: 'Test',
        contact: 'visitor@example.com',
        message: "Need a quote\nthis week.",
    ))->toMail($owner);

    $html = View::make($mail->view[0], $mail->viewData)->render();

    expect($html)
        ->toContain('New website message')
        ->toContain('Handyman Mike')
        ->toContain('visitor@example.com')
        ->toContain('Need a quote')
        ->toContain('border-radius:16px')
        ->toContain('Open the lead')
        ->not->toContain('Regards')
        ->not->toContain('If you’re having trouble clicking');

    expect($mail->subject)->toBe('Message from the Handyman Mike website');
});

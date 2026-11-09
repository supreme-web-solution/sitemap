<?php

use App\Enums\LeadStatus;
use App\Enums\TeamRole;
use App\Mail\WeeklyTeamReportEmail;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

test('the weekly report command queues a branded snapshot with dashboard links', function () {
    Mail::fake();

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $team = Team::factory()->create(['name' => "William Victor's Team"]);
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    Lead::factory()->for($team)->create(['status' => LeadStatus::New]);
    Lead::factory()->for($team)->create(['status' => LeadStatus::Won, 'updated_at' => now()->subDays(2)]);
    $openLead = Lead::factory()->for($team)->create(['status' => LeadStatus::Interested]);
    Invoice::factory()->create([
        'team_id' => $team->id,
        'lead_id' => $openLead->id,
        'total_cents' => 25000,
    ]);

    Artisan::call('reports:send-weekly');

    Mail::assertQueued(WeeklyTeamReportEmail::class, function (WeeklyTeamReportEmail $mail) use ($team, $owner) {
        if (! $mail->team->is($team)) {
            return false;
        }

        expect($mail->hasTo($owner->email))->toBeTrue()
            ->and($mail->envelope()->subject)->toContain("William Victor's Team")
            ->and($mail->stats['dashboardUrl'])->toContain('/dashboard')
            ->and($mail->stats['reportsUrl'])->toContain('/reports')
            ->and($mail->stats['openLeads'])->toBeGreaterThanOrEqual(1);

        $html = $mail->render();

        expect($html)
            ->toContain('Open the dashboard')
            ->toContain($mail->stats['dashboardUrl'])
            ->toContain('Your weekly snapshot');

        return true;
    });
});

test('weekly report email renders metric cards for the team', function () {
    $team = Team::factory()->create(['name' => 'Agency Co']);

    $mail = new WeeklyTeamReportEmail($team, [
        'openLeads' => 46,
        'wonThisWeek' => 1,
        'outstanding' => '$0.00',
        'dashboardUrl' => 'https://example.test/agency/dashboard',
        'reportsUrl' => 'https://example.test/agency/reports',
    ]);

    $html = $mail->render();

    expect($html)
        ->toContain('46')
        ->toContain('1')
        ->toContain('$0.00')
        ->toContain('https://example.test/agency/dashboard')
        ->toContain('View reports');
});

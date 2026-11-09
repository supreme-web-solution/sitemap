<?php

namespace App\Console\Commands;

use App\Enums\LeadStatus;
use App\Mail\WeeklyTeamReportEmail;
use App\Models\Invoice;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Description('Email each team a weekly snapshot of pipeline and invoices')]
class SendClientReports extends Command
{
    /**
     * @var string
     */
    protected $signature = 'reports:send-weekly';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Team::query()->orderBy('id')->eachById(function (Team $team) {
            $email = $team->brand()['email'] ?: $team->owner()?->email;

            if (blank($email)) {
                return;
            }

            $won = $team->leads()->where('status', LeadStatus::Won)->where('updated_at', '>=', now()->subWeek())->count();
            $outstanding = Invoice::formatCents((int) $team->invoices()->outstanding()->sum('total_cents'));
            $openLeads = $team->leads()->whereNotIn('status', [LeadStatus::Won, LeadStatus::Lost])->count();

            Mail::to($email)->queue(new WeeklyTeamReportEmail($team, [
                'openLeads' => $openLeads,
                'wonThisWeek' => $won,
                'outstanding' => $outstanding,
                'dashboardUrl' => route('dashboard', ['current_team' => $team->slug]),
                'reportsUrl' => route('reports.index', ['current_team' => $team->slug]),
            ]));
        }, 50);

        return self::SUCCESS;
    }
}

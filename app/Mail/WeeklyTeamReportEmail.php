<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyTeamReportEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{openLeads: int, wonThisWeek: int, outstanding: string, dashboardUrl: string, reportsUrl: string}  $stats
     */
    public function __construct(
        public Team $team,
        public array $stats,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Weekly snapshot for :team', ['team' => $this->team->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.weekly-team-report',
            text: 'mail.weekly-team-report-text',
        );
    }
}

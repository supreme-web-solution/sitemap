<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;
use Laravel\Horizon\Horizon;

Schedule::command('invoices:run-recurring')->dailyAt('06:00')->withoutOverlapping()->description('Issue due recurring invoices');
Schedule::command('invoices:send-reminders')->dailyAt('09:00')->withoutOverlapping()->description('Email reminders for overdue invoices');
Schedule::command('sequences:run')->everyFiveMinutes()->withoutOverlapping()->description('Send due follow-up sequence emails');
Schedule::command('mail:sync-inboxes')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->when(fn () => (bool) config('inbox.imap_sync_enabled'))
    ->description('Poll connected IMAP mailboxes for outreach replies');
Schedule::command('sites:monitor')->hourly()->withoutOverlapping()->description('Check published site uptime and crawl for broken links');
Schedule::command('sites:backup')->dailyAt('03:00')->withoutOverlapping()->description('Store JSON backups of published sites');
Schedule::command('reports:send-weekly')->weeklyOn(1, '07:00')->withoutOverlapping()->description('Email weekly pipeline snapshots');
Schedule::command('automations:run-scheduled')->dailyAt('04:30')->withoutOverlapping()->description('Queue scheduled agency workflow steps (website re-audits)');
Schedule::command('horizon:snapshot')->everyFiveMinutes()->when(fn () => class_exists(Horizon::class))->description('Horizon metrics snapshots');

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

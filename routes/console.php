<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:run-recurring')->dailyAt('06:00')->withoutOverlapping()->description('Issue due recurring invoices');
Schedule::command('invoices:send-reminders')->dailyAt('09:00')->withoutOverlapping()->description('Email reminders for overdue invoices');
Schedule::command('sequences:run')->everyFiveMinutes()->withoutOverlapping()->description('Send due follow-up sequence emails');
Schedule::command('sites:monitor')->hourly()->withoutOverlapping()->description('Check published site uptime and crawl for broken links');
Schedule::command('sites:backup')->dailyAt('03:00')->withoutOverlapping()->description('Store JSON backups of published sites');
Schedule::command('reports:send-weekly')->weeklyOn(1, '07:00')->withoutOverlapping()->description('Email weekly pipeline snapshots');

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

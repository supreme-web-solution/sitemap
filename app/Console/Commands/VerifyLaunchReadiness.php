<?php

namespace App\Console\Commands;

use App\Services\Operations\LaunchCheck;
use App\Services\Operations\LaunchReadinessChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;

#[Description('Verify production readiness: env, queue, schedule, domains, mail, payments, and optional live HTTP probes')]
class VerifyLaunchReadiness extends Command
{
    protected $signature = 'ops:verify
                            {--live : HTTP probes against APP_URL (TLS gate, dashboard reachability, Mindcase)}
                            {--json : Print the report as JSON instead of a table}';

    protected $description = 'Run launch-readiness checks for Forge/production cutover';

    public function handle(LaunchReadinessChecker $checker): int
    {
        $report = $checker->run(live: (bool) $this->option('live'));

        if ($this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report->ok() ? self::SUCCESS : self::FAILURE;
        }

        $summary = $report->summary();
        $this->components->info(sprintf(
            'Launch readiness: %d pass, %d warn, %d fail%s',
            $summary['pass'],
            $summary['warn'],
            $summary['fail'],
            $report->ok() ? '' : ' — fix failures before cutover',
        ));

        $this->table(
            ['Status', 'Area', 'Check', 'Detail'],
            collect($report->checks)->map(fn (LaunchCheck $check) => [
                strtoupper($check->status),
                $check->category,
                $check->title,
                $check->hint ? "{$check->message} — {$check->hint}" : $check->message,
            ])->all(),
        );

        if (! $report->ok()) {
            $this->components->warn('Re-run with --live on the server after env fixes: php artisan ops:verify --live');
        }

        return $report->ok() ? self::SUCCESS : self::FAILURE;
    }
}

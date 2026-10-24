<?php

namespace App\Services\Operations;

use App\Models\MailConnection;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Static checks (and optional live HTTP probes) before production cutover.
 */
class LaunchReadinessChecker
{
    /**
     * Artisan commands that must appear in the schedule.
     *
     * @var list<string>
     */
    protected const REQUIRED_SCHEDULED = [
        'invoices:run-recurring',
        'invoices:send-reminders',
        'sequences:run',
        'sites:monitor',
        'sites:backup',
        'reports:send-weekly',
    ];

    /**
     * @param  bool|null  $production  When set, overrides app()->environment('production').
     */
    public function run(bool $live = false, ?bool $production = null): LaunchReadinessReport
    {
        $production ??= app()->environment('production');
        $checks = [];

        $this->checkPlatform($checks, $production);
        $this->checkQueueAndWorkers($checks, $production);
        $this->checkSchedule($checks);
        $this->checkDomainsAndTls($checks, $production);
        $this->checkBrandedPreview($checks);
        $this->checkMailAndInbox($checks, $production);
        $this->checkPayments($checks);
        $this->checkIntegrations($checks, $production);
        $this->checkDatabaseHealth($checks);

        if ($live) {
            $this->checkLiveHttp($checks, $production);
        }

        return new LaunchReadinessReport($checks);
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkPlatform(array &$checks, bool $production): void
    {
        $checks[] = filled(config('app.key'))
            ? $this->pass('platform', 'app_key', 'Application key', 'APP_KEY is set.')
            : $this->fail('platform', 'app_key', 'Application key', 'APP_KEY is missing.', 'Run php artisan key:generate');

        $url = rtrim((string) config('app.url'), '/');
        $looksLocal = Str::contains($url, ['localhost', '127.0.0.1', '.test', '.local']);

        if ($production && $looksLocal) {
            $checks[] = $this->fail('platform', 'app_url', 'Public URL', 'APP_URL still points at a local host.', 'Set APP_URL to the HTTPS dashboard URL on Forge.');
        } else {
            $checks[] = $this->pass('platform', 'app_url', 'Public URL', $url !== '' ? "APP_URL is {$url}." : 'APP_URL is set.');
        }

        $admins = config('admin.emails', []);

        if ($production && $admins === []) {
            $checks[] = $this->fail('platform', 'admin_emails', 'Platform admins', 'ADMIN_EMAILS is empty.', 'Add comma-separated admin emails so someone can open /admin.');
        } elseif ($admins === []) {
            $checks[] = $this->warn('platform', 'admin_emails', 'Platform admins', 'ADMIN_EMAILS is empty.', 'Set ADMIN_EMAILS before go-live.');
        } else {
            $checks[] = $this->pass('platform', 'admin_emails', 'Platform admins', count($admins).' admin email(s) configured.');
        }
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkQueueAndWorkers(array &$checks, bool $production): void
    {
        $connection = (string) config('queue.default');

        if ($production && $connection === 'sync') {
            $checks[] = $this->fail('queue', 'queue_driver', 'Queue driver', 'QUEUE_CONNECTION=sync in production.', 'Run a queue worker with redis or database (see PROJECT_CHECKLIST.md).');
        } elseif ($connection === 'database') {
            $checks[] = $this->warn('queue', 'queue_driver', 'Queue driver', 'Using the database queue.', 'Redis is recommended at scale; ensure a Forge daemon runs queue:work.');
        } else {
            $checks[] = $this->pass('queue', 'queue_driver', 'Queue driver', "QUEUE_CONNECTION={$connection}.");
        }

        if ($connection === 'redis') {
            try {
                Redis::connection()->ping();
                $checks[] = $this->pass('queue', 'redis', 'Redis', 'Redis responded to PING.');
            } catch (Throwable $exception) {
                $checks[] = $this->fail('queue', 'redis', 'Redis', 'Could not reach Redis: '.$exception->getMessage(), 'Check REDIS_* env vars and that Redis is running.');
            }
        }

        $retryAfter = (int) config('queue.connections.'.$connection.'.retry_after', 90);
        $workerTimeout = 90;

        if ($retryAfter <= $workerTimeout) {
            $checks[] = $this->warn(
                'queue',
                'retry_after',
                'Queue retry timing',
                "retry_after ({$retryAfter}s) should stay above the worker --timeout ({$workerTimeout}s).",
                'Raise DB_QUEUE_RETRY_AFTER or REDIS retry_after in config/queue.php.',
            );
        } else {
            $checks[] = $this->pass('queue', 'retry_after', 'Queue retry timing', "retry_after is {$retryAfter}s (above worker timeout).");
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = (int) DB::table('failed_jobs')->count();
            $checks[] = $failed > 0
                ? $this->warn('queue', 'failed_jobs', 'Failed queue jobs', "{$failed} row(s) in failed_jobs.", 'Run php artisan queue:failed and retry or flush after fixing the cause.')
                : $this->pass('queue', 'failed_jobs', 'Failed queue jobs', 'No failed_jobs rows.');
        }
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkSchedule(array &$checks): void
    {
        $commands = collect(Schedule::events())
            ->map(fn ($event) => $this->scheduledCommandName($event->command ?? null))
            ->filter()
            ->values();

        $missing = collect(self::REQUIRED_SCHEDULED)
            ->reject(fn (string $name) => $commands->contains(fn (string $scheduled) => str_contains($scheduled, $name)))
            ->values()
            ->all();

        if ($missing !== []) {
            $checks[] = $this->fail(
                'schedule',
                'scheduled_commands',
                'Task scheduler',
                'Missing scheduled commands: '.implode(', ', $missing).'.',
                'Ensure Forge cron runs * * * * * php artisan schedule:run',
            );
        } else {
            $checks[] = $this->pass('schedule', 'scheduled_commands', 'Task scheduler', 'Core billing, sequences, sites, and reports commands are scheduled.');
        }

        $imapEnabled = (bool) config('inbox.imap_sync_enabled');
        $hasMailSync = $commands->contains(fn (string $name) => str_contains($name, 'mail:sync-inboxes'));

        if ($imapEnabled && ! $hasMailSync) {
            $checks[] = $this->fail('schedule', 'mail_sync', 'IMAP sync schedule', 'INBOX_IMAP_SYNC_ENABLED is true but mail:sync-inboxes is not scheduled.');
        } elseif ($imapEnabled) {
            $checks[] = $this->pass('schedule', 'mail_sync', 'IMAP sync schedule', 'mail:sync-inboxes is scheduled (every 5 minutes when enabled).');
        } else {
            $checks[] = $this->warn('schedule', 'mail_sync', 'IMAP sync schedule', 'INBOX_IMAP_SYNC_ENABLED is false.', 'Set true on the server after IMAP mailboxes are connected.');
        }
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkDomainsAndTls(array &$checks, bool $production): void
    {
        $appHosts = config('sites.app_hosts', []);
        $token = (string) config('sites.tls_ask_token');
        $cname = (string) config('sites.cname_target');
        $ip = (string) config('sites.server_ip');

        if ($production && $appHosts === []) {
            $checks[] = $this->fail('domains', 'app_hosts', 'Dashboard hostnames', 'SITES_APP_HOSTS is empty.', 'List every hostname that serves the app (dashboard), comma-separated.');
        } elseif ($appHosts === []) {
            $checks[] = $this->warn('domains', 'app_hosts', 'Dashboard hostnames', 'SITES_APP_HOSTS is empty.', 'Add your Forge app hostname before publishing customer sites.');
        } else {
            $checks[] = $this->pass('domains', 'app_hosts', 'Dashboard hostnames', implode(', ', $appHosts));
        }

        if ($production && $token === '') {
            $checks[] = $this->fail('domains', 'tls_token', 'On-demand TLS', 'SITES_TLS_ASK_TOKEN is empty.', 'Caddy must send this token when calling /internal/domains/allowed.');
        } elseif ($token === '') {
            $checks[] = $this->warn('domains', 'tls_token', 'On-demand TLS', 'SITES_TLS_ASK_TOKEN is not set.');
        } else {
            $checks[] = $this->pass('domains', 'tls_token', 'On-demand TLS', 'SITES_TLS_ASK_TOKEN is configured.');
        }

        if ($production && $cname === '' && $ip === '') {
            $checks[] = $this->fail('domains', 'dns_targets', 'Customer DNS targets', 'Set SITES_CNAME_TARGET and/or SITES_SERVER_IP.', 'Agencies need these values when connecting custom domains.');
        } elseif ($cname === '' && $ip === '') {
            $checks[] = $this->warn('domains', 'dns_targets', 'Customer DNS targets', 'SITES_CNAME_TARGET and SITES_SERVER_IP are both empty.');
        } else {
            $parts = array_filter([
                $cname !== '' ? "CNAME → {$cname}" : null,
                $ip !== '' ? "A → {$ip}" : null,
            ]);
            $checks[] = $this->pass('domains', 'dns_targets', 'Customer DNS targets', implode('; ', $parts));
        }

        if (! Route::has('domains.allowed')) {
            $checks[] = $this->fail('domains', 'tls_route', 'TLS allow endpoint', 'Route domains.allowed is missing.');
        } else {
            $checks[] = $this->pass('domains', 'tls_route', 'TLS allow endpoint', 'GET /internal/domains/allowed is registered.');
        }
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkBrandedPreview(array &$checks): void
    {
        $pending = Team::query()
            ->whereNotNull('preview_hostname')
            ->whereNull('preview_hostname_verified_at')
            ->count();

        $verified = Team::query()
            ->whereNotNull('preview_hostname_verified_at')
            ->count();

        if ($pending > 0) {
            $checks[] = $this->warn(
                'preview',
                'preview_dns',
                'Branded preview hostnames',
                "{$pending} team(s) waiting on DNS/TLS verification; {$verified} verified.",
                'Each agency completes TXT/CNAME on Branding → Preview domain, then Verify.',
            );
        } else {
            $checks[] = $this->pass(
                'preview',
                'preview_dns',
                'Branded preview hostnames',
                $verified > 0 ? "{$verified} verified preview hostname(s)." : 'No pending preview hostname verifications.',
            );
        }
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkMailAndInbox(array &$checks, bool $production): void
    {
        $mailer = (string) config('mail.default');

        if ($production && in_array($mailer, ['log', 'array'], true)) {
            $checks[] = $this->fail('mail', 'mail_driver', 'Outbound mail', "MAIL_MAILER={$mailer} will not deliver email.", 'Configure SMTP, Postmark, Resend, or SES on Forge.');
        } elseif (in_array($mailer, ['log', 'array'], true)) {
            $checks[] = $this->warn('mail', 'mail_driver', 'Outbound mail', "MAIL_MAILER={$mailer} (fine for local dev).");
        } else {
            $checks[] = $this->pass('mail', 'mail_driver', 'Outbound mail', "MAIL_MAILER={$mailer}.");
        }

        $imapSync = (bool) config('inbox.imap_sync_enabled');
        $connections = MailConnection::query()->count();

        if ($imapSync && ! extension_loaded('imap')) {
            $checks[] = $this->fail(
                'inbox',
                'php_imap',
                'PHP IMAP extension',
                'INBOX_IMAP_SYNC_ENABLED is true but ext-imap is not loaded.',
                'Install php-imap on the server (Forge PHP extensions).',
            );
        } elseif ($imapSync) {
            $checks[] = $this->pass('inbox', 'php_imap', 'PHP IMAP extension', 'ext-imap is available.');
        } else {
            $checks[] = $this->warn('inbox', 'php_imap', 'PHP IMAP extension', 'Automatic inbox polling is off.', 'Manual Log reply still works; enable INBOX_IMAP_SYNC_ENABLED when ready.');
        }

        if ($imapSync && $connections === 0) {
            $checks[] = $this->warn('inbox', 'mail_connections', 'Connected inboxes', 'IMAP sync is on but no team has connected a mailbox yet.');
        } elseif ($connections > 0) {
            $checks[] = $this->pass('inbox', 'mail_connections', 'Connected inboxes', "{$connections} team mailbox connection(s).");
        } else {
            $checks[] = $this->pass('inbox', 'mail_connections', 'Connected inboxes', 'No mailboxes connected yet (expected until agencies connect).');
        }
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkPayments(array &$checks): void
    {
        $base = rtrim((string) config('app.url'), '/');
        $routes = [
            'Stripe' => 'webhooks.stripe',
            'PayPal' => 'webhooks.paypal',
            'Paystack' => 'webhooks.paystack',
            'Flutterwave' => 'webhooks.flutterwave',
        ];

        $lines = [];

        foreach ($routes as $label => $name) {
            if (! Route::has($name)) {
                $checks[] = $this->fail('payments', $name, "{$label} webhook route", 'Route is missing.');

                continue;
            }

            $sample = ltrim(route($name, ['key' => 'TEAM_WEBHOOK_KEY'], false), '/');
            $lines[] = "{$label}: {$base}/".str_replace('TEAM_WEBHOOK_KEY', '{team_webhook_key}', $sample);
        }

        if ($lines === []) {
            return;
        }

        $checks[] = $this->pass(
            'payments',
            'webhook_urls',
            'Payment webhooks',
            'Webhook routes are registered.',
            implode("\n", $lines).' — paste each team’s key from Payments settings into {team_webhook_key}.',
        );
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkIntegrations(array &$checks, bool $production): void
    {
        $mindcase = (string) config('services.mindcase.key');

        if ($mindcase === '') {
            $checks[] = $production
                ? $this->fail('integrations', 'mindcase', 'Mindcase API', 'MINDCASE_API_KEY is missing.', 'Lead search and review fetch need Mindcase on production.')
                : $this->warn('integrations', 'mindcase', 'Mindcase API', 'MINDCASE_API_KEY is not set.');
        } else {
            $checks[] = $this->pass('integrations', 'mindcase', 'Mindcase API', 'MINDCASE_API_KEY is set.');
        }

        $hasAi = filled(config('services.anthropic.key')) || filled(config('services.openai.key'));

        $checks[] = $hasAi
            ? $this->pass('integrations', 'ai_keys', 'AI site planning', 'An Anthropic or OpenAI key is configured.')
            : $this->warn('integrations', 'ai_keys', 'AI site planning', 'No AI key found; rule-based copy will be used.', 'Set ANTHROPIC_API_KEY or OPENAI_API_KEY for richer sites.');

        $checks[] = filled(config('services.zernio.base_url'))
            ? $this->pass('integrations', 'zernio', 'Zernio messaging', rtrim((string) config('services.zernio.base_url'), '/'))
            : $this->warn('integrations', 'zernio', 'Zernio messaging', 'ZERNIO_BASE_URL is empty.');
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkDatabaseHealth(array &$checks): void
    {
        try {
            DB::connection()->getPdo();
            $checks[] = $this->pass('platform', 'database', 'Database', 'Connection succeeded.');
        } catch (Throwable $exception) {
            $checks[] = $this->fail('platform', 'database', 'Database', $exception->getMessage());
        }
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function checkLiveHttp(array &$checks, bool $production): void
    {
        $base = rtrim((string) config('app.url'), '/');

        if ($base === '') {
            $checks[] = $this->fail('live', 'app_http', 'Live HTTP probe', 'APP_URL is empty; cannot probe.');

            return;
        }

        try {
            $response = Http::timeout(20)->get($base);
            $ok = $response->successful() || in_array($response->status(), [301, 302, 303, 307, 308], true);
            $checks[] = $ok
                ? $this->pass('live', 'app_http', 'Live HTTP probe', "GET {$base} returned {$response->status()}.")
                : $this->fail('live', 'app_http', 'Live HTTP probe', "GET {$base} returned {$response->status()}.", 'Check Forge site, SSL, and SITES_APP_HOSTS.');
        } catch (Throwable $exception) {
            $checks[] = $this->fail('live', 'app_http', 'Live HTTP probe', $exception->getMessage());
        }

        $token = (string) config('sites.tls_ask_token');

        if ($token === '') {
            return;
        }

        try {
            $withoutToken = Http::timeout(15)->get("{$base}/internal/domains/allowed", ['domain' => 'probe.example']);
            $checks[] = $withoutToken->status() === 404
                ? $this->pass('live', 'tls_gate', 'TLS gate (no token)', 'Endpoint rejects requests without the token.')
                : $this->fail('live', 'tls_gate', 'TLS gate (no token)', 'Expected 404 without token, got '.$withoutToken->status().'.');

            $withToken = Http::timeout(15)->get("{$base}/internal/domains/allowed", [
                'token' => $token,
                'domain' => 'not-a-customer-domain.example',
            ]);
            $checks[] = $withToken->status() === 404
                ? $this->pass('live', 'tls_gate', 'TLS gate (token)', 'Endpoint accepts the token but rejects unknown domains.')
                : $this->fail('live', 'tls_gate', 'TLS gate (token)', 'Expected 404 for unknown domain, got '.$withToken->status().'.');
        } catch (Throwable $exception) {
            $checks[] = $this->fail('live', 'tls_gate', 'TLS gate probe', $exception->getMessage());
        }

        if ($production && filled(config('services.mindcase.key'))) {
            $this->probeMindcase($checks);
        }
    }

    /**
     * @param  list<LaunchCheck>  $checks
     */
    protected function probeMindcase(array &$checks): void
    {
        $base = rtrim((string) config('services.mindcase.base_url'), '/');
        $key = (string) config('services.mindcase.key');

        try {
            $response = Http::withToken($key)
                ->timeout(15)
                ->get("{$base}/jobs/probe-readiness-check");

            $checks[] = in_array($response->status(), [401, 403, 404, 422], true)
                ? $this->pass('live', 'mindcase', 'Mindcase API reachability', "API responded with HTTP {$response->status()} (reachable).")
                : $this->warn('live', 'mindcase', 'Mindcase API reachability', "Unexpected HTTP {$response->status()}; confirm the key manually.");
        } catch (Throwable $exception) {
            $checks[] = $this->fail('live', 'mindcase', 'Mindcase API reachability', $exception->getMessage());
        }
    }

    protected function scheduledCommandName(?string $command): ?string
    {
        if ($command === null || $command === '') {
            return null;
        }

        if (preg_match('/artisan\s+(\S+)/', $command, $matches) === 1) {
            return $matches[1];
        }

        return trim($command);
    }

    protected function pass(string $category, string $id, string $title, string $message, ?string $hint = null): LaunchCheck
    {
        return new LaunchCheck($id, $category, 'pass', $title, $message, $hint);
    }

    protected function warn(string $category, string $id, string $title, string $message, ?string $hint = null): LaunchCheck
    {
        return new LaunchCheck($id, $category, 'warn', $title, $message, $hint);
    }

    protected function fail(string $category, string $id, string $title, string $message, ?string $hint = null): LaunchCheck
    {
        return new LaunchCheck($id, $category, 'fail', $title, $message, $hint);
    }
}

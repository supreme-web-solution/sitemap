<?php

namespace App\Support;

/**
 * Names of the queues the app dispatches to.
 *
 * Run one worker that drains them in priority order, for example:
 * php artisan queue:work --queue=messaging,default,ai,monitoring
 */
final class Queues
{
    /** Customer-facing SMS and WhatsApp; short, time-sensitive. */
    public const MESSAGING = 'messaging';

    /** Lead search, audits, review fetching, email. */
    public const DEFAULT = 'default';

    /** Slow AI work: site generation, chat edits, concepts, summaries. */
    public const AI = 'ai';

    /** Background checks: link crawls, uptime, backups, reports. */
    public const MONITORING = 'monitoring';
}

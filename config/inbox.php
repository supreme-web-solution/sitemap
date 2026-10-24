<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Team email inbox (IMAP)
    |--------------------------------------------------------------------------
    |
    | Agencies connect their reply mailbox with IMAP credentials. Scheduled sync
    | runs when INBOX_IMAP_SYNC_ENABLED is true. Manual "Log reply" on leads
    | works without sync. Outbound outreach sets Message-ID headers so
    | In-Reply-To matching ties synced mail back to leads.
    |
    | Zernio is used for SMS/WhatsApp only — not agency email.
    |
    */

    'imap_sync_enabled' => env('INBOX_IMAP_SYNC_ENABLED', false),

    'sync_interval_minutes' => (int) env('INBOX_SYNC_INTERVAL_MINUTES', 5),

    'syncs_per_minute' => (int) env('INBOX_SYNCS_PER_MINUTE', 6),

    'imap_timeout_seconds' => (int) env('INBOX_IMAP_TIMEOUT_SECONDS', 30),

    'imap_max_messages_per_sync' => (int) env('INBOX_IMAP_MAX_MESSAGES_PER_SYNC', 50),

    'snippet_max_length' => 480,

    'manual_log_snippet_max_length' => 2000,

];

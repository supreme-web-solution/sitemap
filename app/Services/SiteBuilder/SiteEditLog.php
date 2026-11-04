<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Facades\Log;

/**
 * Structured INFO logs for AI site edits (see storage/logs/laravel.log).
 */
class SiteEditLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $step, array $context = []): void
    {
        Log::info('[site-edit] '.$step, $context);
    }
}

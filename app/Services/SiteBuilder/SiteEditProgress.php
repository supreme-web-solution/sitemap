<?php

namespace App\Services\SiteBuilder;

use Illuminate\Support\Facades\Cache;

/**
 * Live build log lines for an in-flight AI site edit (SSE + cache).
 */
class SiteEditProgress
{
    public function __construct(protected int $messageId) {}

    public static function key(int $messageId): string
    {
        return 'site-edit-progress:'.$messageId;
    }

    public function line(string $line): void
    {
        $lines = Cache::get(self::key($this->messageId), []);

        if (! is_array($lines)) {
            $lines = [];
        }

        $lines[] = $line;

        Cache::put(self::key($this->messageId), $lines, now()->addMinutes(15));
    }

    /**
     * @return list<string>
     */
    public static function lines(int $messageId): array
    {
        $lines = Cache::get(self::key($messageId), []);

        return is_array($lines) ? array_values(array_map(strval(...), $lines)) : [];
    }

    public static function forget(int $messageId): void
    {
        Cache::forget(self::key($messageId));
    }
}

<?php

namespace App\Services\SiteBuilder;

/**
 * Request-scoped notes from side effects during an AI edit (blog posts, etc.).
 */
class SiteEditExtras
{
    /** @var list<string> */
    protected static array $lines = [];

    public static function reset(): void
    {
        self::$lines = [];
    }

    public static function add(string $line): void
    {
        $line = trim($line);

        if ($line === '' || in_array($line, self::$lines, true)) {
            return;
        }

        self::$lines[] = $line;
    }

    /**
     * @return list<string>
     */
    public static function lines(): array
    {
        return self::$lines;
    }
}

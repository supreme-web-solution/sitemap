<?php

namespace App\Support;

final class AdminEmails
{
    /**
     * Parse a comma-separated administrator email list (case-insensitive, trimmed).
     *
     * @return list<string>
     */
    public static function parse(?string $raw): array
    {
        return array_values(array_filter(array_map(
            fn (string $email) => strtolower(trim($email)),
            explode(',', (string) $raw),
        )));
    }
}

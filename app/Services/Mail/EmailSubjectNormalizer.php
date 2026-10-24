<?php

namespace App\Services\Mail;

class EmailSubjectNormalizer
{
    /**
     * Strip reply/forward prefixes and normalise whitespace for thread matching.
     */
    public static function normalize(string $subject): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $subject) ?? '');

        while (preg_match('/^(re|fw|fwd):\s*/i', $normalized)) {
            $normalized = trim((string) preg_replace('/^(re|fw|fwd):\s*/i', '', $normalized));
        }

        return mb_strtolower($normalized);
    }
}

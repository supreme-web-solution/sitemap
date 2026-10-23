<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Turn a phone number written any way into E.164 (+15551234567), or null when it is not usable.
     *
     * Ten digits are treated as US numbers.
     */
    public static function e164(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw);
        $hasPlus = str_starts_with(trim($raw), '+');

        if ($digits === '') {
            return null;
        }

        if (! $hasPlus) {
            if (strlen($digits) === 10) {
                $digits = '1'.$digits;
            } elseif (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                // already has the US country code
            } elseif (strlen($digits) < 11) {
                return null;
            }
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
    }

    /**
     * The same number without the plus sign, as WhatsApp expects.
     */
    public static function digits(?string $raw): ?string
    {
        $e164 = self::e164($raw);

        return $e164 === null ? null : substr($e164, 1);
    }
}

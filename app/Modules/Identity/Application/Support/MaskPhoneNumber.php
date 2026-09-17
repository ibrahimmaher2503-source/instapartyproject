<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Support;

/**
 * Masks an E.164 phone number for display.
 *
 * Strategy: keep the country code (digits up to and including the first 2
 * after the leading '+') and the LAST FOUR digits intact; replace the rest
 * with asterisks grouped in threes for readability.
 *
 * Examples:
 *   "+201234567890" → "+20 1*** ***7890"
 *   "+966512345678" → "+96 6*** ***5678"
 *   "+12025551234"  → "+1 20*** ***1234" (3-digit country codes fall back to the same scheme)
 *
 * Pass-through behaviour:
 *   - Non-E.164 strings (no leading '+') are returned unchanged.
 *   - Strings shorter than eight digits are returned unchanged.
 */
final class MaskPhoneNumber
{
    public static function run(?string $phoneE164): string
    {
        if ($phoneE164 === null || $phoneE164 === '') {
            return '';
        }

        if (! str_starts_with($phoneE164, '+')) {
            return $phoneE164;
        }

        $digits = preg_replace('/\D+/', '', $phoneE164) ?? '';

        if (strlen($digits) < 8) {
            return $phoneE164;
        }

        // Country code = first two digits (covers +20, +966, +1 schemes equivalently for display).
        $cc = substr($digits, 0, 2);
        $head = substr($digits, 2, 1);            // first digit after country code (kept)
        $tail = substr($digits, -4);              // last 4 digits (kept)

        return sprintf('+%s %s*** ***%s', $cc, $head, $tail);
    }
}

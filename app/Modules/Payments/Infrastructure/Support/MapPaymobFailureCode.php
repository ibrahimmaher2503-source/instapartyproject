<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Support;

class MapPaymobFailureCode
{
    public static function fromMessage(?string $message): string
    {
        $m = strtolower((string) $message);

        return match (true) {
            in_array($m, ['insufficient_funds', 'declined_by_issuer', 'expired_card', 'fraud_suspected', 'expired_payment_hold'], true) => $m,
            str_contains($m, 'insufficient') => 'insufficient_funds',
            str_contains($m, 'declined') => 'declined_by_issuer',
            str_contains($m, 'expired') => 'expired_card',
            str_contains($m, 'fraud') => 'fraud_suspected',
            default => 'unknown',
        };
    }
}

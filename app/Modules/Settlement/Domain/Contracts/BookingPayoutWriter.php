<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Contracts;

interface BookingPayoutWriter
{
    /** @param array<int, int> $persistedCommissions */
    public function recordCommission(int $bookingItemId, int $commissionMinor, string $currency, array $persistedCommissions = []): void;
}

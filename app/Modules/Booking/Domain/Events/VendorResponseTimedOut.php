<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

final class VendorResponseTimedOut
{
    /** @param list<int> $bookingVendorIds */
    public function __construct(
        public readonly int $bookingId,
        public readonly array $bookingVendorIds,
        public readonly bool $eventAlreadyStarted,
    ) {}
}

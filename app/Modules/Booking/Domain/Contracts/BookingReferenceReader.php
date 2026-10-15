<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Contracts;

interface BookingReferenceReader
{
    public function referenceNoFor(int $bookingId): ?string;

    public function publicIdFor(int $bookingId): ?string;

    public function isVendorRequestPending(string $bookingVendorPublicId, int $vendorUserId): bool;
}

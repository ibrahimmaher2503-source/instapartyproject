<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class SuggestedAlternativeVendorsDTO
{
    public function __construct(
        public int $bookingId,
        public int $adminId,
        public string $bookingVendorPublicId,
        public int $vendorProfileId,
        public string $reason,
        public ?string $idempotencyKey = null,
    ) {}
}

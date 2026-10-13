<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class CoverageAreaDTO
{
    public function __construct(
        public int $vendorProfileId,
        public int $cityId,
        public int $minOrderMinor,
        public string $minOrderCurrency,
        public int $deliveryFeeMinor = 0,
        public string $deliveryFeeCurrency = 'EGP',
    ) {}
}

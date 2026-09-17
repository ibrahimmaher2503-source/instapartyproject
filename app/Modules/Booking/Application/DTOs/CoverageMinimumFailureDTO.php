<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class CoverageMinimumFailureDTO
{
    /**
     * @param  array<string, string>  $vendorBusinessName  ['en' => '...', 'ar' => '...']
     */
    public function __construct(
        public string $vendorPublicId,
        public array $vendorBusinessName,
        public int $minOrderMinor,
        public string $minOrderCurrency,
        public int $currentSubtotalMinor,
        public int $shortfallMinor,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class CoverageMinimumStatusDTO
{
    public function __construct(
        public int $minOrderMinor,
        public string $minOrderCurrency,
        public int $currentSubtotalMinor,
        public bool $meetsMinimum,
        public int $shortfallMinor,
    ) {}
}

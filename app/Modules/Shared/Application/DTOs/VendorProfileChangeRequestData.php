<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\DTOs;

final readonly class VendorProfileChangeRequestData
{
    /**
     * @param  list<VendorProfileChangeRequestItemData>  $items
     */
    public function __construct(
        public int $cycleNumber,
        public array $items,
    ) {}
}

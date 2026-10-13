<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

use App\Modules\Shared\Application\DTOs\VendorProfileChangeRequestData;

interface VendorProfileChangeRequestReader
{
    public function findCurrentOpenForVendorProfile(int $vendorProfileId): ?VendorProfileChangeRequestData;
}

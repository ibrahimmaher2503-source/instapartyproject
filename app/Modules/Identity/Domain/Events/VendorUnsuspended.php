<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class VendorUnsuspended
{
    use Dispatchable;

    public function __construct(
        public VendorProfile $vendorProfile,
        public ?int $unsuspendedBy = null,
    ) {}
}

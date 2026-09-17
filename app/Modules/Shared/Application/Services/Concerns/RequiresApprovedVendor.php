<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services\Concerns;

use App\Modules\Shared\Application\Services\VendorOperationalAccess;

trait RequiresApprovedVendor
{
    public static function canAccess(): bool
    {
        return VendorOperationalAccess::allowed();
    }
}

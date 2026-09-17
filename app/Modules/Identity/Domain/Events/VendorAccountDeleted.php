<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

/**
 * Fired after a vendor self-deletes their account (live audit 2026-06-06
 * §12.2). Scalar payload only — queued listeners cannot serialize models.
 */
class VendorAccountDeleted
{
    public function __construct(
        public readonly int $vendorProfileId,
        public readonly int $userId,
    ) {}
}

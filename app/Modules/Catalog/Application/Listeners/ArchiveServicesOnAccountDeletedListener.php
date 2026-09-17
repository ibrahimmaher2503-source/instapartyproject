<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Listeners;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Events\VendorAccountDeleted;

/**
 * A self-deleted vendor's listings must leave the catalog — same sweep as
 * ArchiveServicesOnTypeRevokedListener, but across every product type.
 */
class ArchiveServicesOnAccountDeletedListener
{
    public function handle(VendorAccountDeleted $event): void
    {
        Service::query()
            ->where('vendor_profile_id', $event->vendorProfileId)
            ->whereIn('status', [ServiceStatus::Draft->value, ServiceStatus::Published->value, ServiceStatus::PendingReview->value])
            ->update(['status' => ServiceStatus::Archived->value]);
    }
}

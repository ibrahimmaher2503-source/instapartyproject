<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Application\Actions;

use App\Modules\TrustSafety\Domain\Models\VendorBadgeAssignment;
use Illuminate\Support\Facades\DB;

class AssignBadgeToVendorAction
{
    public function execute(int $vendorProfileId, int $trustBadgeId, int $assignedBy): VendorBadgeAssignment
    {
        return DB::transaction(function () use ($vendorProfileId, $trustBadgeId, $assignedBy) {
            $assignment = VendorBadgeAssignment::firstOrCreate(
                ['vendor_profile_id' => $vendorProfileId, 'trust_badge_id' => $trustBadgeId],
                ['assigned_by' => $assignedBy, 'assigned_at' => now()],
            );

            return $assignment;
        });
    }
}

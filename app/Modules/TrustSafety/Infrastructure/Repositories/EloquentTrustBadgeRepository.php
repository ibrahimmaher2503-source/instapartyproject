<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Infrastructure\Repositories;

use App\Modules\TrustSafety\Domain\Models\TrustBadge;
use App\Modules\TrustSafety\Domain\Models\VendorBadgeAssignment;
use Illuminate\Database\Eloquent\Collection;

class EloquentTrustBadgeRepository
{
    public function findActiveBadgesForVendor(int $vendorProfileId): Collection
    {
        $badgeIds = VendorBadgeAssignment::where('vendor_profile_id', $vendorProfileId)
            ->pluck('trust_badge_id');

        return TrustBadge::active()
            ->whereIn('id', $badgeIds)
            ->with('media')
            ->orderBy('level')
            ->get();
    }
}

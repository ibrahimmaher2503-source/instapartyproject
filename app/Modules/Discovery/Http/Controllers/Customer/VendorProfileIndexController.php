<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Customer;

use App\Modules\Discovery\Http\Resources\VendorProfileResource;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer - Vendor Browsing
 */
class VendorProfileIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 6), 24);

        $vendors = VendorProfile::with(['primaryCity', 'approvedTypes', 'user'])
            // Count only what a customer can actually see — must stay in lockstep
            // with the published-only listings (VendorBrowsingRepository::servicesFor,
            // statsFor, and the /customer/services vendor filter).
            ->withCount(['services' => fn ($q) => $q->published()])
            ->whereState('approval_status', ApprovedState::class)
            ->orderByDesc('rating_avg')
            ->limit($perPage)
            ->get();

        return ApiResponse::success(VendorProfileResource::collection($vendors));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Http\Controllers;

use App\Modules\TrustSafety\Http\Resources\TrustBadgeResource;
use App\Modules\TrustSafety\Infrastructure\Repositories\EloquentTrustBadgeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @group Trust & Safety
 */
class VendorBadgeController
{
    public function __construct(
        private readonly EloquentTrustBadgeRepository $repository,
    ) {}

    public function show(string $vendorPublicId): JsonResponse
    {
        $vendorRow = DB::table('vendor_profiles')->where('public_id', $vendorPublicId)->firstOrFail();
        $badges = $this->repository->findActiveBadgesForVendor($vendorRow->id);

        return TrustBadgeResource::collection($badges)->response();
    }
}

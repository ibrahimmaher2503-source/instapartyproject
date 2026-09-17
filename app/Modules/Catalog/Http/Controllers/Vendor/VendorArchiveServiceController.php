<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\VendorArchiveServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Vendor - Services
 */
class VendorArchiveServiceController
{
    public function __invoke(Request $request, string $publicId, VendorArchiveServiceAction $action): JsonResponse
    {
        // Route defaults ('type') bind AFTER URI params and controller args are
        // filled positionally, so a (string $type, string $publicId) signature
        // received them swapped → public_id = 'rental' → always 404 (live audit
        // 2026-06-06). Read the default by name instead.
        $type = ProductType::tryFrom((string) $request->route('type'));
        abort_unless($type !== null, 404);

        $vendor = $request->user()->vendorProfile()->firstOrFail();

        // Vendor-scoped so foreign services 404 (existence-hiding, same
        // contract as VendorCrossIsolationTest). The action re-checks
        // ownership as defense-in-depth.
        $service = Service::query()
            ->where('public_id', $publicId)
            ->where('product_type', $type)
            ->where('vendor_profile_id', $vendor->id)
            ->firstOrFail();

        $action->execute($service, $vendor);

        return ApiResponse::success([], status: 204);
    }
}

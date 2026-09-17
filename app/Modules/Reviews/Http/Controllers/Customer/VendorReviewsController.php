<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Http\Controllers\Customer;

use App\Modules\Reviews\Domain\Contracts\VendorReviewRepository;
use App\Modules\Reviews\Http\Resources\CustomerVendorReviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Customer - Vendor Browsing
 */
class VendorReviewsController
{
    public function __construct(private readonly VendorReviewRepository $repo) {}

    public function index(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendorProfileId = DB::table('vendor_profiles')
            ->where('public_id', $vendorPublicId)
            ->value('id');

        if ($vendorProfileId === null) {
            return response()->json([
                'data' => null,
                'meta' => (object) [],
                'errors' => [['code' => 'not_found']],
            ], 404);
        }

        $stats = $this->repo->aggregateApprovedForVendor((int) $vendorProfileId);

        $result = $this->repo->listApprovedForVendor((int) $vendorProfileId, [
            'cursor' => $request->query('cursor'),
            'limit' => (int) ($request->query('limit', 15)),
        ]);

        return response()->json([
            'data' => CustomerVendorReviewResource::collection($result['items']),
            'meta' => [
                'next_cursor' => $result['next_cursor'],
                'rating_avg' => $stats['average'],
                'rating_count' => $stats['count'],
            ],
            'errors' => null,
        ]);
    }
}

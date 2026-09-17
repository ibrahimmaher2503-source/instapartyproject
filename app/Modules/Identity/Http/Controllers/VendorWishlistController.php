<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\AddVendorToWishlistAction;
use App\Modules\Identity\Application\Actions\ListCustomerVendorWishlistAction;
use App\Modules\Identity\Application\Actions\RemoveVendorFromWishlistAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Requests\AddVendorToWishlistRequest;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @group Customer - Wishlist
 */
class VendorWishlistController extends Controller
{
    public function __construct(
        private readonly AddVendorToWishlistAction $addAction,
        private readonly RemoveVendorFromWishlistAction $removeAction,
        private readonly ListCustomerVendorWishlistAction $listAction,
    ) {}

    public function store(AddVendorToWishlistRequest $request): JsonResponse
    {
        try {
            $vendorPublicId = $request->validated('vendor_public_id');
            $wishlist = $this->addAction->execute(
                (int) $request->user()->id,
                $vendorPublicId,
            );

            return ApiResponse::success([
                'public_id' => $wishlist->public_id,
                'vendor_public_id' => $vendorPublicId,
                'saved_at' => $wishlist->created_at?->toISOString(),
            ], [], 201);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 409) {
                return response()->json(['data' => null, 'errors' => ['code' => 'ALREADY_SAVED']], 409);
            }
            throw $e;
        }
    }

    public function destroy(Request $request, string $vendorPublicId): Response
    {
        $this->removeAction->execute(
            (int) $request->user()->id,
            $vendorPublicId,
        );

        return response()->noContent();
    }

    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 20)));

        $result = $this->listAction->execute(
            (int) $request->user()->id,
            $limit,
        );

        $locale = $request->header('Accept-Language', 'en');
        $locale = in_array($locale, ['en', 'ar'], true) ? $locale : 'en';

        $data = $result['items']->map(function ($item) use ($locale): array {
            /** @var VendorProfile|null $vendor */
            $vendor = $item->vendorProfile;

            $vendorName = '';
            if ($vendor !== null) {
                $businessName = $vendor->getTranslations('business_name');
                $vendorName = $businessName[$locale] ?? $businessName['en'] ?? '';
            }

            return [
                'public_id' => $item->public_id,
                'vendor_public_id' => $vendor?->public_id,
                'vendor_name' => $vendorName,
                'vendor_avatar_url' => null,
                'vendor_rating_avg' => $vendor?->rating_avg,
                'saved_at' => $item->created_at?->toISOString(),
            ];
        });

        return ApiResponse::success($data->all(), ['total' => $result['total'], 'next_cursor' => null]);
    }
}

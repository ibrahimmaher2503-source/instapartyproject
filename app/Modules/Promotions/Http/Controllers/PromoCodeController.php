<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers;

use App\Modules\Promotions\Application\Actions\ListActiveCustomerPromotionsAction;
use App\Modules\Promotions\Application\Actions\ValidatePromoCodeAction;
use App\Modules\Promotions\Application\DTOs\ValidatePromoCodeDTO;
use App\Modules\Promotions\Http\Requests\ValidatePromoCodeRequest;
use App\Modules\Promotions\Http\Resources\ActivePromotionResource;
use App\Modules\Promotions\Http\Resources\PromoValidationResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer - Promotions
 */
class PromoCodeController
{
    public function validate(ValidatePromoCodeRequest $request, ValidatePromoCodeAction $action): JsonResponse
    {
        $dto = new ValidatePromoCodeDTO(
            code: $request->validated('code'),
            bookingDraftPublicId: $request->validated('booking_draft_id'),
            cartTotalMinor: $request->validated('cart_total_minor'),
            cartCurrency: $request->validated('cart_currency'),
            userId: $request->user()->id,
        );

        return (new PromoValidationResource($action->execute($dto)))->response();
    }

    /**
     * Feature 054 — US11 (C4). List active customer-visible promotions.
     *
     * @queryParam scope string Optional scope filter: global|category|vendor|service. Example: vendor
     * @queryParam vendor_public_id string Optional ULID to scope to a single vendor's promotions. Example: 01JC7G3F0AY3T9V5K0X9N1QY3S
     * @queryParam limit integer Page size (1-50). Example: 20
     * @queryParam cursor string Opaque cursor from a previous response's meta.next_cursor.
     */
    public function listActive(Request $request, ListActiveCustomerPromotionsAction $action): JsonResponse
    {
        $request->validate([
            'scope' => ['nullable', 'string', 'in:global,category,vendor,service'],
            'vendor_public_id' => ['nullable', 'string', 'size:26'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);

        $page = $action->execute(
            scope: $request->query('scope'),
            vendorPublicId: $request->query('vendor_public_id'),
            limit: (int) $request->integer('limit', 20),
            cursor: $request->query('cursor'),
        );

        return ApiResponse::success(
            ActivePromotionResource::collection($page->getCollection()),
            ['next_cursor' => $page->nextCursor()?->encode()],
        );
    }
}

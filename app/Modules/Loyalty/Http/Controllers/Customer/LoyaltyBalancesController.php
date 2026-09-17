<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Customer;

use App\Modules\Loyalty\Application\Actions\ListCustomerLoyaltyBalancesAction;
use App\Modules\Loyalty\Http\Resources\LoyaltyBalancesResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer - Loyalty
 */
class LoyaltyBalancesController
{
    public function __construct(
        private readonly ListCustomerLoyaltyBalancesAction $action,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $balances = $this->action->execute((int) $request->user()->id);
        $data = LoyaltyBalancesResource::collection(collect($balances))->resolve($request);

        $totalPoints = array_sum(array_column($balances, 'balance_points'));
        $totalMinorEquivalent = array_sum(array_column($balances, 'balance_minor_equivalent'));

        return ApiResponse::success($data, [
            'total_vendors' => count($balances),
            'total_points' => $totalPoints,
            'total_minor_equivalent' => $totalMinorEquivalent,
            'total_currency' => 'EGP',
        ]);
    }
}

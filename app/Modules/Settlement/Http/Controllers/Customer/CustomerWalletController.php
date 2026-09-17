<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Controllers\Customer;

use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Http\Resources\CustomerRefundResource;
use App\Modules\Settlement\Http\Resources\CustomerWalletResource;
use App\Modules\Settlement\Http\Resources\CustomerWalletTransactionResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Customer - Wallet
 */
class CustomerWalletController
{
    public function balance(Request $request): JsonResponse
    {
        $wallet = $this->customerWallet($request);

        if ($wallet === null) {
            return ApiResponse::success(['public_id' => null, 'balance_minor' => 0, 'balance_formatted' => '0.00 EGP', 'pending_refunds_minor' => 0, 'pending_refunds_formatted' => '0.00 EGP']);
        }

        $pendingRefunds = Refund::query()
            ->whereHas('booking', fn ($q) => $q->where('customer_id', $request->user()->id))
            ->where('status', 'pending')
            ->sum('amount_minor');

        return ApiResponse::success(
            (new CustomerWalletResource($wallet))->withPendingRefunds((int) $pendingRefunds)->toArray($request)
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $request->validate([
            'direction' => ['nullable', 'string', Rule::in(['credit', 'debit'])],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);

        $wallet = $this->customerWallet($request);

        if ($wallet === null) {
            return ApiResponse::success([]);
        }

        $query = WalletLedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->orderByDesc('id');

        if ($request->filled('direction')) {
            $query->where('direction', $request->string('direction'));
        }

        $entries = $query->cursorPaginate(20);

        return CustomerWalletTransactionResource::collection($entries)->response();
    }

    public function refunds(Request $request): JsonResponse
    {
        $refunds = Refund::query()
            ->with('booking')
            ->whereHas('booking', fn ($q) => $q->where('customer_id', $request->user()->id))
            ->orderByDesc('created_at')
            ->cursorPaginate(20);

        return CustomerRefundResource::collection($refunds)->response();
    }

    private function customerWallet(Request $request): ?Wallet
    {
        return Wallet::query()
            ->where('owner_type', 'customer')
            ->where('owner_id', $request->user()->id)
            ->first();
    }
}

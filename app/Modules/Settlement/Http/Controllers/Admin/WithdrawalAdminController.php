<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Controllers\Admin;

use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\RejectWithdrawalAction;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Http\Requests\Admin\RejectWithdrawalRequest;
use App\Modules\Settlement\Http\Resources\WithdrawalResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin
 */
class WithdrawalAdminController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string'],
            'vendor_public_id' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Withdrawal::query()->latest('requested_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('vendor_public_id')) {
            $query->whereHas('vendorProfile', fn ($q) => $q->where('public_id', $request->string('vendor_public_id')->toString()));
        }

        $paginator = $query->paginate((int) $request->input('per_page', 20));

        return ApiResponse::success(
            WithdrawalResource::collection($paginator->getCollection()),
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    public function approve(Request $request, string $publicId, ApproveWithdrawalAction $action): JsonResponse
    {
        $withdrawal = Withdrawal::query()->where('public_id', $publicId)->firstOrFail();
        $withdrawal = $action->execute($withdrawal, $request->user());

        return ApiResponse::success(new WithdrawalResource($withdrawal));
    }

    public function reject(RejectWithdrawalRequest $request, string $publicId, RejectWithdrawalAction $action): JsonResponse
    {
        $withdrawal = Withdrawal::query()->where('public_id', $publicId)->firstOrFail();
        $withdrawal = $action->execute($withdrawal, $request->validated('reason'), $request->user());

        return ApiResponse::success(new WithdrawalResource($withdrawal));
    }
}

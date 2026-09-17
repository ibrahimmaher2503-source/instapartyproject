<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\ListVendorServicesAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Http\Resources\ServiceBaseResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Vendor - Services
 */
class VendorServiceListController
{
    public function __invoke(Request $request, ListVendorServicesAction $action): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::in(['rental', 'sale', 'digital'])],
            'status' => ['nullable', Rule::in(array_column(ServiceStatus::cases(), 'value'))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $vendor = $request->user()->vendorProfile()->firstOrFail();
        $type = $request->filled('type') ? ProductType::from($request->string('type')->toString()) : null;
        $status = $request->filled('status') ? ServiceStatus::from($request->string('status')->toString()) : null;
        $perPage = (int) $request->input('per_page', 25);

        $paginator = $action->execute($vendor, $type, $status, $perPage);

        $paginatorArray = $paginator->toArray();

        return ApiResponse::success(
            ServiceBaseResource::collection($paginator->getCollection()),
            [
                'current_page' => $paginatorArray['current_page'],
                'last_page' => $paginatorArray['last_page'],
                'per_page' => $paginatorArray['per_page'],
                'total' => $paginatorArray['total'],
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\DownloadTemplateAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Vendor - Excel Import
 */
class DownloadImportTemplateController
{
    public function __invoke(Request $request, string $type, DownloadTemplateAction $action): BinaryFileResponse
    {
        abort_unless(collect(ProductType::cases())->map->value->contains($type), 422, 'Invalid product type.');
        if ($type === ProductType::Digital->value && $request->routeIs('vendor.catalog.import.template')) {
            abort_unless(config('catalog.digital_services_enabled', true), 404);
            abort_unless(
                $request->user()?->vendorProfile?->approvedTypes()
                    ->where('product_type', ProductType::Digital->value)
                    ->exists(),
                404,
            );
        }

        return $action->execute(ProductType::from($type));
    }
}

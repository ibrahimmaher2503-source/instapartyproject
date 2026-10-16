<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\DownloadTemplateAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Shared\Application\Services\VendorOperationalAccess;
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
        if ($request->routeIs('vendor.catalog.import.template', 'vendor.catalog.import.template.web')) {
            abort_unless($request->user()?->hasRole('vendor') && VendorOperationalAccess::allowed(), 403);
            abort_if($type === ProductType::Digital->value && ! config('catalog.digital_services_enabled', true), 404);
            abort_unless(
                $request->user()?->vendorProfile?->approvedTypes()
                    ->where('product_type', $type)
                    ->exists(),
                404,
            );
        }

        return $action->execute(ProductType::from($type));
    }
}

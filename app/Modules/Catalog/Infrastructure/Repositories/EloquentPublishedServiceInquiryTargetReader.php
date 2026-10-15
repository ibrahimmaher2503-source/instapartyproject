<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Contracts\PublishedServiceInquiryTargetReader;
use App\Modules\Catalog\Domain\DTOs\PublishedServiceInquiryTarget;
use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Database\Eloquent\Builder;

final class EloquentPublishedServiceInquiryTargetReader implements PublishedServiceInquiryTargetReader
{
    public function find(string $servicePublicId): ?PublishedServiceInquiryTarget
    {
        $service = Service::query()
            ->displayable()
            ->whereHas('vendor', fn (Builder $vendors): Builder => $vendors
                ->approved()
                ->whereHas('approvedTypes', fn (Builder $types): Builder => $types
                    ->whereColumn('vendor_approved_product_types.product_type', 'services.product_type')))
            ->with('vendor')
            ->where('public_id', $servicePublicId)
            ->first();

        if ($service === null || $service->vendor === null) {
            return null;
        }

        return new PublishedServiceInquiryTarget(
            serviceId: $service->id,
            servicePublicId: $service->public_id,
            vendorProfileId: $service->vendor_profile_id,
            serviceNameEn: $service->getTranslation('name', 'en') ?: '',
            serviceNameAr: $service->getTranslation('name', 'ar') ?: '',
            vendorNameEn: $service->vendor->getTranslation('business_name', 'en') ?: '',
            vendorNameAr: $service->vendor->getTranslation('business_name', 'ar') ?: '',
        );
    }
}

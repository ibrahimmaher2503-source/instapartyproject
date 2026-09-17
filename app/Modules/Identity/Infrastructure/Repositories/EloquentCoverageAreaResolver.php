<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Repositories;

use App\Modules\Booking\Application\DTOs\CoverageAreaDTO;
use App\Modules\Booking\Domain\Contracts\CoverageAreaResolver;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;

final class EloquentCoverageAreaResolver implements CoverageAreaResolver
{
    public function resolveForCity(int $cityId, array $vendorProfileIds): array
    {
        if ($vendorProfileIds === []) {
            return [];
        }

        $rows = VendorCoverageArea::query()
            ->where('city_id', $cityId)
            ->whereIn('vendor_profile_id', $vendorProfileIds)
            ->get(['vendor_profile_id', 'city_id', 'min_order_minor', 'min_order_currency']);

        $byVendor = [];
        foreach ($rows as $row) {
            $byVendor[$row->vendor_profile_id] = new CoverageAreaDTO(
                vendorProfileId: $row->vendor_profile_id,
                cityId: $row->city_id,
                minOrderMinor: (int) $row->min_order_minor,
                minOrderCurrency: $row->min_order_currency,
            );
        }

        foreach ($vendorProfileIds as $id) {
            $byVendor[$id] ??= null;
        }

        return $byVendor;
    }
}

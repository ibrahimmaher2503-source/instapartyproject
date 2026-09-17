<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Services;

use App\Modules\Booking\Application\DTOs\CoverageMinimumStatusDTO;
use App\Modules\Booking\Domain\Contracts\CoverageAreaResolver;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Support\Facades\DB;

final class CoverageMinimumStatusService
{
    public function __construct(
        private readonly CoverageAreaResolver $coverageAreaResolver,
    ) {}

    /**
     * Compute min-order status for each booking vendor.
     *
     * @return array<int, array{status: CoverageMinimumStatusDTO|null, reason_code: string|null}>
     *                                                                                            Keyed by booking_vendor.id.
     */
    public function statusFor(Booking $booking): array
    {
        $result = [];

        if ($booking->address === null || $booking->address->city_id === null) {
            foreach ($booking->vendors as $bookingVendor) {
                $result[$bookingVendor->id] = ['status' => null, 'reason_code' => 'delivery_city_unknown'];
            }

            return $result;
        }

        $cityId = (int) $booking->address->city_id;

        $vendorProfileIds = $booking->vendors
            ->pluck('vendor_profile_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $coverageMap = $vendorProfileIds !== []
            ? $this->coverageAreaResolver->resolveForCity($cityId, $vendorProfileIds)
            : [];

        $bookingVendorIds = $booking->vendors->pluck('id')->all();

        $subtotalRows = DB::table('booking_items')
            ->whereIn('booking_vendor_id', $bookingVendorIds)
            ->whereIn('product_type', [ProductType::Rental->value, ProductType::Sale->value])
            ->groupBy('booking_vendor_id')
            ->selectRaw('booking_vendor_id, SUM(line_total_minor) as subtotal_minor')
            ->get()
            ->keyBy('booking_vendor_id');

        $allDigital = DB::table('booking_items')
            ->whereIn('booking_vendor_id', $bookingVendorIds)
            ->groupBy('booking_vendor_id')
            ->selectRaw('booking_vendor_id, MIN(product_type) as min_type, MAX(product_type) as max_type')
            ->get()
            ->keyBy('booking_vendor_id');

        foreach ($booking->vendors as $bookingVendor) {
            $vendorProfileId = (int) $bookingVendor->vendor_profile_id;
            $vendorId = $bookingVendor->id;

            $coverageDto = $coverageMap[$vendorProfileId] ?? null;

            if ($coverageDto === null) {
                $result[$vendorId] = ['status' => null, 'reason_code' => 'no_coverage_row'];

                continue;
            }

            $hasNonDigital = $subtotalRows->has($vendorId);

            if (! $hasNonDigital) {
                $itemInfo = $allDigital->get($vendorId);
                if ($itemInfo !== null) {
                    $result[$vendorId] = ['status' => null, 'reason_code' => 'not_applicable_digital_only'];

                    continue;
                }
                $result[$vendorId] = ['status' => null, 'reason_code' => 'not_applicable_digital_only'];

                continue;
            }

            $currentSubtotal = (int) $subtotalRows->get($vendorId)->subtotal_minor;
            $meetsMinimum = $currentSubtotal >= $coverageDto->minOrderMinor;
            $shortfall = $meetsMinimum ? 0 : ($coverageDto->minOrderMinor - $currentSubtotal);

            $result[$vendorId] = [
                'status' => new CoverageMinimumStatusDTO(
                    minOrderMinor: $coverageDto->minOrderMinor,
                    minOrderCurrency: $coverageDto->minOrderCurrency,
                    currentSubtotalMinor: $currentSubtotal,
                    meetsMinimum: $meetsMinimum,
                    shortfallMinor: $shortfall,
                ),
                'reason_code' => null,
            ];
        }

        return $result;
    }
}

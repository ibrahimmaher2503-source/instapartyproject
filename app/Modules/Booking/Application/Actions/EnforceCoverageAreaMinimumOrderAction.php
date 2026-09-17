<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\CoverageMinimumFailureDTO;
use App\Modules\Booking\Domain\Contracts\CoverageAreaResolver;
use App\Modules\Booking\Domain\Exceptions\BelowCoverageMinimumException;
use App\Modules\Booking\Domain\Exceptions\CurrencyMismatchException;
use App\Modules\Booking\Domain\Exceptions\DeliveryCityRequiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Support\Facades\DB;

final class EnforceCoverageAreaMinimumOrderAction
{
    public function __construct(
        private readonly CoverageAreaResolver $coverageAreaResolver,
    ) {}

    public function execute(int $bookingId): void
    {
        /** @var Booking $booking */
        $booking = Booking::with(['vendors.vendor', 'address'])->findOrFail($bookingId);

        if ($booking->address === null || $booking->address->city_id === null) {
            throw new DeliveryCityRequiredException;
        }

        $cityId = (int) $booking->address->city_id;

        $vendorProfileIds = $booking->vendors
            ->pluck('vendor_profile_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($vendorProfileIds === []) {
            return;
        }

        $coverageMap = $this->coverageAreaResolver->resolveForCity($cityId, $vendorProfileIds);

        $bookingVendorIds = $booking->vendors->pluck('id')->all();

        $subtotalRows = DB::table('booking_items')
            ->whereIn('booking_vendor_id', $bookingVendorIds)
            ->whereIn('product_type', [ProductType::Rental->value, ProductType::Sale->value])
            ->groupBy('booking_vendor_id')
            ->selectRaw('booking_vendor_id, SUM(line_total_minor) as subtotal_minor')
            ->get()
            ->keyBy('booking_vendor_id');

        $failures = [];

        foreach ($booking->vendors as $bookingVendor) {
            $vendorProfileId = (int) $bookingVendor->vendor_profile_id;
            $coverageDto = $coverageMap[$vendorProfileId] ?? null;

            if ($coverageDto === null) {
                continue;
            }

            if ($coverageDto->minOrderMinor === 0) {
                continue;
            }

            $subtotalRow = $subtotalRows->get($bookingVendor->id);
            $currentSubtotal = $subtotalRow !== null ? (int) $subtotalRow->subtotal_minor : 0;

            if ($currentSubtotal === 0) {
                continue;
            }

            $bookingCurrency = 'EGP';
            if ($coverageDto->minOrderCurrency !== $bookingCurrency) {
                throw new CurrencyMismatchException($bookingCurrency, $coverageDto->minOrderCurrency);
            }

            if ($currentSubtotal < $coverageDto->minOrderMinor) {
                $vendorProfile = $bookingVendor->vendor;
                $businessName = $vendorProfile?->getRawOriginal('business_name') ?? [];
                if (is_string($businessName)) {
                    $businessName = json_decode($businessName, true) ?? [];
                }

                $failures[] = new CoverageMinimumFailureDTO(
                    vendorPublicId: (string) ($vendorProfile?->public_id ?? $bookingVendor->public_id),
                    vendorBusinessName: (array) $businessName,
                    minOrderMinor: $coverageDto->minOrderMinor,
                    minOrderCurrency: $coverageDto->minOrderCurrency,
                    currentSubtotalMinor: $currentSubtotal,
                    shortfallMinor: $coverageDto->minOrderMinor - $currentSubtotal,
                );
            }
        }

        if ($failures === []) {
            return;
        }

        usort($failures, fn (CoverageMinimumFailureDTO $a, CoverageMinimumFailureDTO $b) => strcmp($a->vendorPublicId, $b->vendorPublicId));

        throw new BelowCoverageMinimumException($failures);
    }
}

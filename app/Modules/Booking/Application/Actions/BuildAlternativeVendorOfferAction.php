<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Contracts\CoverageAreaResolver;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Domain\Contracts\CommissionRateResolver;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use DomainException;

/**
 * Builds the exact, price-preserving replacement offer shown to the customer.
 * The same builder is used again at acceptance to recheck eligibility and quote.
 */
class BuildAlternativeVendorOfferAction
{
    public function __construct(
        private readonly CatalogServiceReader $catalogReader,
        private readonly CoverageAreaResolver $coverageResolver,
        private readonly CommissionRateResolver $commissionRateResolver,
    ) {}

    /**
     * @param  array<string, string>|null  $selectedServicePublicIds  Keyed by booking item public ULID.
     * @return array<string, mixed>
     */
    public function execute(
        BookingVendor $sourceAllocation,
        int $candidateVendorProfileId,
        ?array $selectedServicePublicIds = null,
    ): array {
        $booking = $sourceAllocation->booking()->with('address')->firstOrFail();
        $candidateVendor = VendorProfile::query()
            ->approved()
            ->whereNull('deleted_at')
            ->with('user')
            ->find($candidateVendorProfileId);

        if ($candidateVendor === null || $booking->vendors()->where('vendor_profile_id', $candidateVendorProfileId)->exists()) {
            $this->unavailable();
        }

        $items = $sourceAllocation->items()->orderBy('id')->get();
        if ($items->isEmpty()) {
            $this->unavailable();
        }

        $usedServiceIds = [];
        $offerItems = [];
        $physicalSubtotal = Money::ofMinor(0, (string) $booking->total_currency);
        $itemSubtotal = Money::ofMinor(0, (string) $booking->total_currency);

        foreach ($items as $item) {
            /** @var BookingItem $item */
            $sourceService = $this->catalogReader->findBookingServiceById((int) $item->service_id);
            if ($sourceService === null
                || $sourceService->vendorProfileId !== (int) $sourceAllocation->vendor_profile_id
                || $sourceService->productType !== $item->product_type
                || $item->unit_price_currency !== $booking->total_currency
                || $item->line_total_currency !== $booking->total_currency) {
                $this->unavailable();
            }

            $calculatedLineTotal = Money::ofMinor((int) $item->unit_price_minor, (string) $item->unit_price_currency)
                ->multipliedBy((int) $item->quantity)
                ->getMinorAmount()
                ->toInt();
            if ($calculatedLineTotal !== (int) $item->line_total_minor) {
                $this->unavailable();
            }

            $startsAt = $item->effective_starts_at ?? $booking->event_starts_at;
            $endsAt = $item->effective_ends_at ?? $booking->event_ends_at;
            $packageSnapshot = data_get($item->type_snapshot, 'service_package');
            $excluded = [$sourceService->publicId, ...array_values($usedServiceIds)];

            if (is_array($packageSnapshot)) {
                $code = $packageSnapshot['code'] ?? null;
                if (! is_string($code)
                    || (int) ($packageSnapshot['price_minor'] ?? -1) !== (int) $item->unit_price_minor
                    || (string) ($packageSnapshot['currency'] ?? '') !== (string) $item->unit_price_currency) {
                    $this->unavailable();
                }

                $eligible = $this->catalogReader->findEligibleReplacementServicesForPackage(
                    $candidateVendorProfileId,
                    $sourceService->categoryId,
                    $sourceService->productType,
                    (int) $item->quantity,
                    $startsAt,
                    $endsAt,
                    $excluded,
                );
            } else {
                $eligible = $this->catalogReader->findEligibleReplacementServices(
                    $candidateVendorProfileId,
                    $sourceService->categoryId,
                    $sourceService->productType,
                    (int) $item->unit_price_minor,
                    (string) $item->unit_price_currency,
                    (int) $item->quantity,
                    $startsAt,
                    $endsAt,
                    $excluded,
                );
            }

            $eligible = array_values(array_filter(
                $eligible,
                fn ($service): bool => $this->catalogReader->isServiceEligibleForOccasion($service->id, (int) $booking->occasion_id),
            ));

            $selectedPublicId = $selectedServicePublicIds[$item->public_id] ?? null;
            $replacementService = null;
            $replacementPackage = null;
            foreach ($eligible as $service) {
                if ($selectedPublicId !== null && $service->publicId !== $selectedPublicId) {
                    continue;
                }

                $package = null;
                if (is_array($packageSnapshot)) {
                    $package = $this->catalogReader->findEquivalentActivePackageForService(
                        $service->publicId,
                        (string) $packageSnapshot['code'],
                        (int) $item->unit_price_minor,
                        (string) $item->unit_price_currency,
                    );
                    if ($package === null) {
                        continue;
                    }
                }

                $replacementService = $service;
                $replacementPackage = $package;
                break;
            }

            if ($replacementService === null) {
                $this->unavailable();
            }

            $usedServiceIds[$item->public_id] = $replacementService->publicId;
            $itemSubtotal = $itemSubtotal->plus(Money::ofMinor((int) $item->line_total_minor, (string) $item->line_total_currency));
            if (in_array($item->product_type, [ProductType::Rental, ProductType::Sale], true)) {
                $physicalSubtotal = $physicalSubtotal->plus(Money::ofMinor((int) $item->line_total_minor, (string) $item->line_total_currency));
            }

            $commissionBps = $this->commissionRateResolver->resolve($sourceService->categoryId, $sourceService->productType) ?? 0;
            $commissionMinor = Money::ofMinor((int) $item->line_total_minor, (string) $item->line_total_currency)
                ->multipliedBy($commissionBps)
                ->dividedBy(10000, RoundingMode::HALF_EVEN)
                ->getMinorAmount()
                ->toInt();

            $offerItems[] = [
                'booking_item_public_id' => $item->public_id,
                'source_service_public_id' => $sourceService->publicId,
                'product_type' => $sourceService->productType->value,
                'quantity' => (int) $item->quantity,
                'starts_at' => $startsAt?->copy()->utc()->toIso8601String(),
                'ends_at' => $endsAt?->copy()->utc()->toIso8601String(),
                'unit_price_minor' => (int) $item->unit_price_minor,
                'line_total_minor' => (int) $item->line_total_minor,
                'currency' => (string) $item->line_total_currency,
                'commission_bps' => $commissionBps,
                'commission_minor' => $commissionMinor,
                'replacement_service' => [
                    'id' => $replacementService->id,
                    'public_id' => $replacementService->publicId,
                    'name' => $replacementPackage?->name ?? [
                        'en' => $replacementService->nameEn,
                        'ar' => $replacementService->nameAr,
                    ],
                    'package' => $replacementPackage?->snapshot(),
                ],
            ];
        }

        if ($itemSubtotal->getMinorAmount()->toInt() !== (int) $sourceAllocation->subtotal_minor
            || $sourceAllocation->subtotal_currency !== $booking->total_currency
            || $sourceAllocation->delivery_fee_currency !== $booking->total_currency) {
            $this->unavailable();
        }

        $deliveryFeeMinor = (int) $sourceAllocation->delivery_fee_minor;
        if ($physicalSubtotal->isGreaterThan(Money::ofMinor(0, (string) $booking->total_currency))) {
            $cityId = $booking->address?->city_id;
            if ($cityId === null) {
                $this->unavailable();
            }
            $coverage = $this->coverageResolver->resolveForCity((int) $cityId, [$candidateVendorProfileId])[$candidateVendorProfileId] ?? null;
            if ($coverage === null
                || $coverage->deliveryFeeMinor !== $deliveryFeeMinor
                || $coverage->deliveryFeeCurrency !== $sourceAllocation->delivery_fee_currency
                || $coverage->minOrderCurrency !== $booking->total_currency
                || $physicalSubtotal->isLessThan(Money::ofMinor($coverage->minOrderMinor, $coverage->minOrderCurrency))) {
                $this->unavailable();
            }
        } elseif ($deliveryFeeMinor !== 0) {
            $this->unavailable();
        }

        $offer = [
            'candidate_vendor' => [
                'public_id' => $candidateVendor->public_id,
                'business_name' => [
                    'en' => $candidateVendor->getTranslation('business_name', 'en', useFallbackLocale: false),
                    'ar' => $candidateVendor->getTranslation('business_name', 'ar', useFallbackLocale: false),
                ],
            ],
            'response_deadline' => $sourceAllocation->response_deadline?->copy()->utc()->toIso8601String(),
            'subtotal_minor' => $itemSubtotal->getMinorAmount()->toInt(),
            'delivery_fee_minor' => $deliveryFeeMinor,
            'currency' => (string) $booking->total_currency,
            'total_minor' => $itemSubtotal->plus(Money::ofMinor($deliveryFeeMinor, (string) $booking->total_currency))->getMinorAmount()->toInt(),
            'items' => $offerItems,
        ];
        $offer['offer_hash'] = hash('sha256', json_encode($offer, JSON_THROW_ON_ERROR));

        return $offer;
    }

    /** @return array<string, mixed> */
    public function sourceSnapshot(BookingVendor $sourceAllocation): array
    {
        $sourceVendor = $sourceAllocation->vendor()->firstOrFail();

        return [
            'booking_vendor_public_id' => $sourceAllocation->public_id,
            'vendor_profile_id' => (int) $sourceAllocation->vendor_profile_id,
            'vendor_public_id' => $sourceVendor->public_id,
            'sub_status' => $sourceAllocation->sub_status->value,
            'response_deadline' => $sourceAllocation->response_deadline?->copy()->utc()->toIso8601String(),
            'subtotal_minor' => (int) $sourceAllocation->subtotal_minor,
            'subtotal_currency' => $sourceAllocation->subtotal_currency,
            'delivery_fee_minor' => (int) $sourceAllocation->delivery_fee_minor,
            'delivery_fee_currency' => $sourceAllocation->delivery_fee_currency,
            'commission_minor' => (int) $sourceAllocation->commission_minor,
            'commission_currency' => $sourceAllocation->commission_currency,
            'vendor_payout_minor' => (int) $sourceAllocation->vendor_payout_minor,
            'vendor_payout_currency' => $sourceAllocation->vendor_payout_currency,
            'items' => $sourceAllocation->items()->orderBy('id')->get()->map(fn (BookingItem $item): array => [
                'public_id' => $item->public_id,
                'service_id' => (int) $item->service_id,
                'service_public_id' => $this->catalogReader->findBookingServiceById((int) $item->service_id)?->publicId,
                'product_type' => $item->product_type->value,
                'quantity' => (int) $item->quantity,
                'unit_price_minor' => (int) $item->unit_price_minor,
                'unit_price_currency' => $item->unit_price_currency,
                'line_total_minor' => (int) $item->line_total_minor,
                'line_total_currency' => $item->line_total_currency,
                'type_snapshot' => $item->type_snapshot,
            ])->values()->all(),
        ];
    }

    private function unavailable(): never
    {
        throw new DomainException(__('booking::booking.intervention.error.alternative_vendor_unavailable'));
    }
}

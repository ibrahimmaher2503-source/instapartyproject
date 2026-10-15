<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Application\DTOs\ServiceReadDTO;
use App\Modules\Booking\Application\Services\BookingTransportPricingService;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Contracts\CoverageAreaResolver;
use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Exceptions\CurrencyMismatchException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Settlement\Domain\Contracts\CommissionRateResolver;
use Brick\Money\Money;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddItemToBookingAction
{
    public function __construct(
        private readonly CatalogServiceReader $catalogReader,
        private readonly CommissionRateResolver $commissionRateResolver,
        private readonly BookingTransportPricingService $transportPricing,
        private readonly CoverageAreaResolver $coverageResolver,
    ) {}

    public function execute(AddBookingItemDTO $dto): BookingItem
    {
        // MariaDB ER_CHECKREAD needs the entire transaction restarted with a fresh snapshot.
        return DB::transaction(function () use ($dto): BookingItem {
            $booking = Booking::query()->whereKey($dto->bookingId)->lockForUpdate()->first();

            // Cross-customer access is 404, not 403 — no existence leak (same
            // convention as every other customer booking endpoint; IDOR audit
            // 2026-06-06).
            if ($booking === null || $booking->customer_id !== $dto->customerId) {
                $this->abortWith(Response::HTTP_NOT_FOUND, 'Booking not found');
            }
            if (! ($booking->lifecycle_status instanceof DraftState)) {
                $this->abortWith(Response::HTTP_CONFLICT, 'Booking is not in draft status');
            }

            $service = $this->catalogReader->findPublishedById($dto->serviceId);
            if ($service === null) {
                $this->abortWith(Response::HTTP_NOT_FOUND, 'Service not found or unavailable');
            }

            $package = $dto->packagePublicId === null
                ? null
                : $this->catalogReader->findActivePackageForService($service->publicId, $dto->packagePublicId, lockForUpdate: true);
            if ($dto->packagePublicId !== null && $package === null) {
                $this->abortWith(Response::HTTP_UNPROCESSABLE_ENTITY, __('storefront.wizard.validation.package_invalid'));
            }

            if ($service->productType === ProductType::Rental && $dto->quantity !== 1) {
                $this->abortWith(Response::HTTP_UNPROCESSABLE_ENTITY, __('storefront.wizard.validation.rental_quantity_must_be_one'));
            }

            if (! $this->catalogReader->isServiceEligibleForOccasion($service->id, (int) $booking->occasion_id)) {
                $this->abortWith(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('storefront.wizard.validation.service_occasion_mismatch'),
                );
            }

            $this->transportPricing->snapshotBaseFee($booking);

            // One currency per draft (mobile audit 2026-06-06): every item must
            // match the booking's currency, otherwise mismatched minor units
            // would be summed as if they were the same money. The app surfaces
            // this 422 through its addToCartErrorKey path.
            $unitPriceMinor = $package?->priceMinor ?? $service->basePriceMinor;
            $unitPriceCurrency = $package?->currency ?? $service->basePriceCurrency;
            if ($unitPriceCurrency !== $booking->total_currency) {
                $this->abortWith(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    "Service currency {$unitPriceCurrency} does not match the booking currency {$booking->total_currency}",
                );
            }

            $effectiveStart = $dto->effectiveStartsAt ?? $booking->event_starts_at;
            $effectiveEnd = $dto->effectiveEndsAt ?? $booking->event_ends_at;

            if ($service->productType === ProductType::Rental) {
                if ($effectiveEnd === null && $effectiveStart !== null && $service->defaultRentalDurationHours > 0) {
                    $effectiveEnd = $effectiveStart->copy()->addHours($service->defaultRentalDurationHours);
                }
                if ($effectiveStart === null || $effectiveEnd === null) {
                    $this->abortWith(Response::HTTP_UNPROCESSABLE_ENTITY, __('storefront.wizard.validation.rental_duration_required'));
                }
                if ($effectiveEnd->lessThanOrEqualTo($effectiveStart)) {
                    $this->abortWith(Response::HTTP_UNPROCESSABLE_ENTITY, __('storefront.wizard.validation.times_invalid'));
                }
                if ($this->catalogReader->hasRentalAvailabilityBlock($service->id, $effectiveStart, $effectiveEnd)) {
                    $this->abortWith(Response::HTTP_CONFLICT, 'Service is unavailable for the requested dates');
                }
                $reservationId = $this->reserveRental($service, $dto, $effectiveStart, $effectiveEnd);
            } else {
                $reservationId = match ($service->productType) {
                    ProductType::Sale => $this->reserveSale($service, $dto),
                    ProductType::Digital => null,
                };
            }

            $initialStatus = match ($service->productType) {
                ProductType::Rental => 'pending_delivery',
                ProductType::Sale => 'pending',
                ProductType::Digital => 'pending',
            };

            $vendor = BookingVendor::firstOrCreate(
                ['booking_id' => $booking->id, 'vendor_profile_id' => $service->vendorProfileId],
                [
                    'public_id' => (string) Str::ulid(),
                    'sub_status' => 'pending',
                    'subtotal_currency' => $service->basePriceCurrency,
                    'delivery_fee_currency' => $service->basePriceCurrency,
                    'commission_currency' => $service->basePriceCurrency,
                    'vendor_payout_currency' => $service->basePriceCurrency,
                ]
            );

            $requiresDelivery = match ($service->productType) {
                ProductType::Rental, ProductType::Sale => true,
                ProductType::Digital => false,
            };

            if ($requiresDelivery && ! $vendor->items()->whereIn('product_type', [ProductType::Rental->value, ProductType::Sale->value])->exists()) {
                $cityId = $booking->address?->city_id;
                $coverage = $cityId === null ? null : ($this->coverageResolver->resolveForCity((int) $cityId, [$service->vendorProfileId])[$service->vendorProfileId] ?? null);

                if ($coverage !== null) {
                    if ($coverage->deliveryFeeMinor > 0 && $coverage->deliveryFeeCurrency !== $booking->total_currency) {
                        throw new CurrencyMismatchException($booking->total_currency, $coverage->deliveryFeeCurrency);
                    }

                    // Snapshot once per vendor delivery, independent of platform transport pricing.
                    $vendor->update([
                        'delivery_fee_minor' => $coverage->deliveryFeeMinor,
                        'delivery_fee_currency' => $coverage->deliveryFeeCurrency,
                    ]);
                }
            }

            $lineTotal = Money::ofMinor($unitPriceMinor, $unitPriceCurrency)
                ->multipliedBy($dto->quantity)
                ->getMinorAmount()
                ->toInt();
            $commissionBps = $this->commissionRateResolver->resolve($service->categoryId, $service->productType) ?? 0;

            $typeSnapshot = $package === null ? null : [
                'service_id' => $service->id,
                'service_public_id' => $service->publicId,
                'product_type' => $service->productType->value,
                'unit_price_minor' => $unitPriceMinor,
                'unit_price_currency' => $unitPriceCurrency,
                'service_package' => $package->snapshot(),
            ];

            $item = BookingItem::create([
                'public_id' => (string) Str::ulid(),
                'booking_vendor_id' => $vendor->id,
                'service_id' => $service->id,
                'product_type' => $service->productType->value,
                'name_snapshot' => $package?->name ?? ['en' => $service->nameEn, 'ar' => $service->nameAr],
                'unit_price_minor' => $unitPriceMinor,
                'unit_price_currency' => $unitPriceCurrency,
                'line_total_minor' => $lineTotal,
                'line_total_currency' => $unitPriceCurrency,
                'commission_currency' => $unitPriceCurrency,
                'quantity' => $dto->quantity,
                'effective_starts_at' => $effectiveStart,
                'effective_ends_at' => $effectiveEnd,
                'customization_data' => $dto->customizationData,
                'type_snapshot' => $typeSnapshot,
                'item_status' => $initialStatus,
                'commission_bps' => $commissionBps,
            ]);

            if ($reservationId !== null) {
                DB::table('service_inventory_reservations')
                    ->where('id', $reservationId)
                    ->update(['booking_item_id' => $item->id]);
            }

            $item->setRelation('bookingVendor', $vendor);
            DB::afterCommit(fn () => event(new BookingItemAdded($item)));

            return $item;
        }, 3);
    }

    private function reserveRental(
        ServiceReadDTO $service,
        AddBookingItemDTO $dto,
        Carbon $start,
        Carbon $end,
    ): int {
        DB::table('services')->where('id', $service->id)->lockForUpdate()->first();

        $overlapping = DB::table('service_inventory_reservations')
            ->where('service_id', $service->id)
            ->where(fn ($query) => $query
                ->where('status', ReservationStatus::Confirmed->value)
                ->orWhere(fn ($held) => $held
                    ->where('status', ReservationStatus::Held->value)
                    ->where('expires_at', '>', now())))
            ->where('reserved_starts_at', '<', $end)
            ->where('reserved_ends_at', '>', $start)
            ->lockForUpdate()
            ->first();

        if ($overlapping !== null) {
            $this->abortWith(Response::HTTP_CONFLICT, 'Service is unavailable for the requested dates');
        }

        return (int) DB::table('service_inventory_reservations')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'service_id' => $service->id,
            'user_id' => $dto->customerId,
            'product_type' => ProductType::Rental->value,
            'hold_type' => HoldType::Cart->value,
            'status' => ReservationStatus::Held->value,
            'reserved_starts_at' => $start,
            'reserved_ends_at' => $end,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(HoldType::Cart->ttlMinutes()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reserveSale(ServiceReadDTO $service, AddBookingItemDTO $dto): int
    {
        DB::table('services')->where('id', $service->id)->lockForUpdate()->first();
        $stockQuantity = DB::table('service_sale_details')
            ->where('service_id', $service->id)
            ->lockForUpdate()
            ->value('stock_quantity');

        if ($stockQuantity !== null) {
            $heldReservations = DB::table('service_inventory_reservations')
                ->where('service_id', $service->id)
                ->where(fn ($query) => $query
                    ->where('status', ReservationStatus::Confirmed->value)
                    ->orWhere(fn ($held) => $held
                        ->where('status', ReservationStatus::Held->value)
                        ->where('expires_at', '>', now())))
                ->select('quantity')
                ->lockForUpdate()
                ->get();
            $heldCount = 0;
            foreach ($heldReservations as $reservation) {
                $heldCount += (int) $reservation->quantity;
            }

            $available = (int) $stockQuantity - $heldCount;

            if ($available < $dto->quantity) {
                $this->abortWith(Response::HTTP_CONFLICT, 'Insufficient stock');
            }
        }

        return (int) DB::table('service_inventory_reservations')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'service_id' => $service->id,
            'user_id' => $dto->customerId,
            'product_type' => ProductType::Sale->value,
            'hold_type' => HoldType::Cart->value,
            'status' => ReservationStatus::Held->value,
            'quantity' => $dto->quantity,
            'expires_at' => now()->addMinutes(HoldType::Cart->ttlMinutes()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function abortWith(int $status, string $message): never
    {
        throw new HttpResponseException(
            response()->json(['data' => null, 'meta' => (object) [], 'errors' => ['message' => $message]], $status)
        );
    }
}

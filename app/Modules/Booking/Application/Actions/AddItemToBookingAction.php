<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Application\DTOs\ServiceReadDTO;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Settlement\Domain\Contracts\CommissionRateResolver;
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
    ) {}

    public function execute(AddBookingItemDTO $dto): BookingItem
    {
        return DB::transaction(function () use ($dto): BookingItem {
            $booking = Booking::find($dto->bookingId);

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

            // One currency per draft (mobile audit 2026-06-06): every item must
            // match the booking's currency, otherwise mismatched minor units
            // would be summed as if they were the same money. The app surfaces
            // this 422 through its addToCartErrorKey path.
            if ($service->basePriceCurrency !== $booking->total_currency) {
                $this->abortWith(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    "Service currency {$service->basePriceCurrency} does not match the booking currency {$booking->total_currency}",
                );
            }

            $effectiveStart = $dto->effectiveStartsAt ?? $booking->event_starts_at;
            $effectiveEnd = $dto->effectiveEndsAt ?? $booking->event_ends_at;

            if ($service->productType === ProductType::Rental) {
                if ($effectiveStart === null || $effectiveEnd === null) {
                    $this->abortWith(Response::HTTP_UNPROCESSABLE_ENTITY, 'Rental items require event_starts_at and event_ends_at');
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

            $lineTotal = $service->basePriceMinor * $dto->quantity;
            $commissionBps = $this->commissionRateResolver->resolve($service->categoryId, $service->productType) ?? 0;

            $item = BookingItem::create([
                'public_id' => (string) Str::ulid(),
                'booking_vendor_id' => $vendor->id,
                'service_id' => $service->id,
                'product_type' => $service->productType->value,
                'name_snapshot' => ['en' => $service->nameEn, 'ar' => $service->nameAr],
                'unit_price_minor' => $service->basePriceMinor,
                'unit_price_currency' => $service->basePriceCurrency,
                'line_total_minor' => $lineTotal,
                'line_total_currency' => $service->basePriceCurrency,
                'commission_currency' => $service->basePriceCurrency,
                'quantity' => $dto->quantity,
                'effective_starts_at' => $effectiveStart,
                'effective_ends_at' => $effectiveEnd,
                'customization_data' => $dto->customizationData,
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
        });
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
            ->count();

        if ($overlapping > 0) {
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

        $heldCount = (int) DB::table('service_inventory_reservations')
            ->where('service_id', $service->id)
            ->where(fn ($query) => $query
                ->where('status', ReservationStatus::Confirmed->value)
                ->orWhere(fn ($held) => $held
                    ->where('status', ReservationStatus::Held->value)
                    ->where('expires_at', '>', now())))
            ->sum('quantity');

        if ($service->stockQuantity !== null) {
            $available = $service->stockQuantity - $heldCount;

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

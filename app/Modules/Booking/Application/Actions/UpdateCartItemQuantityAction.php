<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use Brick\Money\Money;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class UpdateCartItemQuantityAction
{
    public function __construct(private readonly CatalogServiceReader $catalogReader) {}

    public function execute(int $bookingId, int $customerId, string $itemPublicId, int $quantity): void
    {
        DB::transaction(function () use ($bookingId, $customerId, $itemPublicId, $quantity): void {
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->first();
            if ($booking === null || $booking->customer_id !== $customerId) {
                $this->abortWith(Response::HTTP_NOT_FOUND, 'Booking not found');
            }
            if (! ($booking->lifecycle_status instanceof DraftState)) {
                $this->abortWith(Response::HTTP_CONFLICT, 'Booking is not in draft status');
            }

            $item = BookingItem::query()
                ->where('public_id', $itemPublicId)
                ->whereHas('bookingVendor', fn ($query) => $query->where('booking_id', $bookingId))
                ->lockForUpdate()
                ->first();
            if ($item === null) {
                $this->abortWith(Response::HTTP_NOT_FOUND, 'Item not found');
            }

            $reservationChanged = match ($item->product_type) {
                ProductType::Rental => $this->assertRentalQuantityUnchanged($item->quantity, $quantity),
                ProductType::Sale => $this->updateSaleReservation($booking, $item, $quantity),
                ProductType::Digital => false,
            };

            $lineTotal = Money::ofMinor($item->unit_price_minor, $item->unit_price_currency)->multipliedBy($quantity);
            $lineTotalMinor = $lineTotal->getMinorAmount()->toInt();
            $itemChanged = $item->quantity !== $quantity
                || $item->line_total_minor !== $lineTotalMinor
                || $item->line_total_currency !== $item->unit_price_currency;

            if (! $itemChanged && ! $reservationChanged) {
                return;
            }

            if ($itemChanged) {
                $item->forceFill([
                    'quantity' => $quantity,
                    'line_total_minor' => $lineTotalMinor,
                    'line_total_currency' => $item->unit_price_currency,
                ])->save();
            }

            $item->loadMissing('bookingVendor');
            DB::afterCommit(fn () => event(new BookingItemAdded($item)));
        });
    }

    private function assertRentalQuantityUnchanged(int $currentQuantity, int $quantity): bool
    {
        if ($currentQuantity !== $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('validation.in', ['attribute' => __('storefront.service.quantity')]),
            ]);
        }

        return false;
    }

    private function updateSaleReservation(Booking $booking, BookingItem $item, int $quantity): bool
    {
        $serviceRow = DB::table('services')->where('id', $item->service_id)->lockForUpdate()->first();
        if ($serviceRow === null) {
            $this->abortWith(Response::HTTP_CONFLICT, 'Service is no longer available');
        }

        $reservation = DB::table('service_inventory_reservations')
            ->where('booking_item_id', $item->id)
            ->where('service_id', $item->service_id)
            ->where('product_type', ProductType::Sale->value)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($reservation !== null && $reservation->status === ReservationStatus::Confirmed->value) {
            $this->abortWith(Response::HTTP_CONFLICT, 'Inventory reservation is no longer editable');
        }
        if ($reservation !== null && $reservation->hold_type !== HoldType::Cart->value) {
            $this->abortWith(Response::HTTP_CONFLICT, 'Inventory reservation is no longer editable');
        }

        $reservationIsActive = $reservation !== null
            && $reservation->status === ReservationStatus::Held->value
            && $reservation->expires_at !== null
            && now()->lt($reservation->expires_at);

        if ($reservationIsActive && $reservation !== null && $quantity <= (int) $reservation->quantity) {
            if ($quantity === (int) $reservation->quantity) {
                return false;
            }

            DB::table('service_inventory_reservations')->where('id', $reservation->id)->update([
                'quantity' => $quantity,
                'updated_at' => now(),
            ]);

            return true;
        }

        $service = $this->catalogReader->findPublishedById((int) $item->service_id);
        if ($service === null || $service->productType !== ProductType::Sale) {
            throw ValidationException::withMessages(['quantity' => __('storefront.service.add_error')]);
        }

        $heldReservations = DB::table('service_inventory_reservations')
            ->where('service_id', $item->service_id)
            ->where(fn ($query) => $query
                ->where('status', ReservationStatus::Confirmed->value)
                ->orWhere(fn ($held) => $held
                    ->where('status', ReservationStatus::Held->value)
                    ->where('expires_at', '>', now())));
        if ($reservationIsActive && $reservation !== null) {
            $heldReservations->where('id', '<>', $reservation->id);
        }
        $heldQuantity = (int) $heldReservations->sum('quantity');

        if ($service->stockQuantity !== null && $service->stockQuantity - $heldQuantity < $quantity) {
            throw ValidationException::withMessages(['quantity' => __('storefront.service.add_error')]);
        }

        $hold = [
            'quantity' => $quantity,
            'expires_at' => now()->addMinutes(HoldType::Cart->ttlMinutes()),
            'updated_at' => now(),
        ];

        if ($reservation !== null && $reservation->status === ReservationStatus::Held->value) {
            DB::table('service_inventory_reservations')->where('id', $reservation->id)->update($hold);
        } else {
            DB::table('service_inventory_reservations')->insert($hold + [
                'public_id' => (string) Str::ulid(),
                'service_id' => $item->service_id,
                'user_id' => $booking->customer_id,
                'product_type' => ProductType::Sale->value,
                'hold_type' => HoldType::Cart->value,
                'status' => ReservationStatus::Held->value,
                'booking_item_id' => $item->id,
                'created_at' => now(),
            ]);
        }

        return true;
    }

    private function abortWith(int $status, string $message): never
    {
        throw new HttpResponseException(
            response()->json(['data' => null, 'meta' => (object) [], 'errors' => ['message' => $message]], $status)
        );
    }
}

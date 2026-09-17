<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\CustomerModificationDecisionDTO;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Events\CustomerModificationDecided;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem; // used in applyAdd
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerConfirmModifiedBookingAction
{
    /** Fields a vendor may change on an existing item via a modification. */
    private const ALLOWED_UPDATE_PAYLOAD_KEYS = [
        'unit_price_minor',
        'unit_price_currency',
        'quantity',
        'effective_starts_at',
        'effective_ends_at',
        'customization_data',
    ];

    /** Fields a vendor may supply when adding a new item via a modification. */
    private const ALLOWED_ADD_PAYLOAD_KEYS = [
        'service_id',
        'product_type',
        'unit_price_minor',
        'unit_price_currency',
        'quantity',
        'effective_starts_at',
        'effective_ends_at',
        'customization_data',
    ];

    public function execute(CustomerModificationDecisionDTO $dto): Booking
    {
        $cached = $this->getCachedIdempotencyResponse($dto);
        if ($cached !== null) {
            return $cached;
        }

        return DB::transaction(function () use ($dto): Booking {
            $booking = Booking::query()->where('id', $dto->bookingId)->lockForUpdate()->firstOrFail();

            // 404, not 403 — no existence leak (IDOR audit 2026-06-06).
            abort_if(
                $booking->customer_id !== $dto->customerId,
                Response::HTTP_NOT_FOUND
            );

            /** @var BookingModification|null $modification */
            $modification = BookingModification::with(['items', 'bookingVendor'])
                ->where('public_id', $dto->modificationPublicId)
                ->whereHas('bookingVendor', fn ($q) => $q->where('booking_id', $booking->id))
                ->lockForUpdate()
                ->first();

            abort_if($modification === null, Response::HTTP_NOT_FOUND);

            abort_if(
                $modification->status !== ModificationStatus::Pending,
                Response::HTTP_CONFLICT,
                __('booking.modification_actions.not_pending'),
            );

            abort_if(
                $modification->expires_at === null || $modification->expires_at->lessThanOrEqualTo(now('UTC')),
                Response::HTTP_CONFLICT,
                __('booking.modification_actions.expired'),
            );

            /** @var BookingVendor $bookingVendor */
            $bookingVendor = $modification->bookingVendor;

            if ($dto->decision === 'accepted') {
                $this->applyModificationItems($modification);

                $modification->update([
                    'status' => ModificationStatus::CustomerAccepted,
                    'customer_decision_at' => now(),
                ]);

                $this->recalculateVendorSubtotal($bookingVendor);
                $this->recalculateBookingTotal($booking);

                // Advance this vendor's sub_status to accepted
                $bookingVendor->update([
                    'sub_status' => VendorSubStatus::Accepted,
                    'responded_at' => now(),
                ]);

                StateTransition::create([
                    'transitionable_type' => BookingVendor::class,
                    'transitionable_id' => $bookingVendor->id,
                    'from_state' => VendorSubStatus::Modified->value,
                    'to_state' => VendorSubStatus::Accepted->value,
                    'trigger_kind' => 'customer',
                    'triggered_by' => $dto->customerId,
                ]);

                $allAccepted = ! BookingVendor::where('booking_id', $booking->id)
                    ->where('sub_status', '!=', VendorSubStatus::Accepted->value)
                    ->exists();

                if ($allAccepted) {
                    $booking->update([
                        'lifecycle_status' => ConfirmedState::class,
                        'confirmed_at' => now(),
                    ]);

                    StateTransition::create([
                        'transitionable_type' => Booking::class,
                        'transitionable_id' => $booking->id,
                        'from_state' => LifecycleStatus::CustomerReview->value,
                        'to_state' => LifecycleStatus::Confirmed->value,
                        'trigger_kind' => 'system',
                    ]);
                } else {
                    $booking->update(['lifecycle_status' => VendorReviewState::class]);

                    StateTransition::create([
                        'transitionable_type' => Booking::class,
                        'transitionable_id' => $booking->id,
                        'from_state' => LifecycleStatus::CustomerReview->value,
                        'to_state' => LifecycleStatus::VendorReview->value,
                        'trigger_kind' => 'system',
                    ]);
                }
            } else {
                $modification->update([
                    'status' => ModificationStatus::CustomerRejected,
                    'customer_decision_at' => now(),
                ]);

                if ($dto->rejectionReason !== null) {
                    $modification->rejection_reason = $dto->rejectionReason;
                    $modification->save();
                }

                $bookingVendor->update(['sub_status' => VendorSubStatus::Pending]);

                StateTransition::create([
                    'transitionable_type' => BookingVendor::class,
                    'transitionable_id' => $bookingVendor->id,
                    'from_state' => VendorSubStatus::Modified->value,
                    'to_state' => VendorSubStatus::Pending->value,
                    'trigger_kind' => 'customer',
                    'triggered_by' => $dto->customerId,
                ]);

                $booking->update(['lifecycle_status' => VendorReviewState::class]);

                StateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => LifecycleStatus::CustomerReview->value,
                    'to_state' => LifecycleStatus::VendorReview->value,
                    'trigger_kind' => 'system',
                ]);
            }

            $booking->refresh();
            $bookingConfirmed = $booking->lifecycle_status instanceof ConfirmedState;

            DB::afterCommit(function () use ($modification, $dto, $booking, $bookingConfirmed): void {
                event(new CustomerModificationDecided($modification, $dto->decision));
                if ($bookingConfirmed) {
                    event(new BookingConfirmed($booking));
                }
                $this->storeIdempotencyResponse($dto, $booking);
            });

            return $booking;
        });
    }

    private function applyModificationItems(BookingModification $modification): void
    {
        foreach ($modification->items as $modItem) {
            /** @var BookingModificationItem $modItem */
            match ($modItem->change_kind) {
                ModificationChangeKind::Update => $this->applyUpdate($modItem),
                ModificationChangeKind::Add => $this->applyAdd($modItem, $modification->bookingVendor()->firstOrFail()),
                ModificationChangeKind::Remove => $this->applyRemove($modItem),
            };
        }
    }

    private function applyUpdate(BookingModificationItem $modItem): void
    {
        if ($modItem->target_booking_item_id === null) {
            return;
        }

        $item = BookingItem::find($modItem->target_booking_item_id);
        if ($item !== null) {
            $safePayload = array_intersect_key(
                $modItem->payload,
                array_flip(self::ALLOWED_UPDATE_PAYLOAD_KEYS)
            );
            $item->update($safePayload);
            $item->update(['line_total_minor' => $item->unit_price_minor * $item->quantity]);
        }
    }

    private function applyAdd(BookingModificationItem $modItem, BookingVendor $bookingVendor): void
    {
        $safePayload = array_intersect_key(
            $modItem->payload,
            array_flip(self::ALLOWED_ADD_PAYLOAD_KEYS)
        );

        $serviceId = (int) ($safePayload['service_id'] ?? 0);
        $service = Service::query()
            ->with('saleDetail')
            ->whereKey($serviceId)
            ->lockForUpdate()
            ->first();

        abort_if($service === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Service is unavailable');
        abort_if(
            $service->vendor_profile_id !== $bookingVendor->vendor_profile_id,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Service does not belong to this vendor',
        );
        abort_unless(
            $service->status instanceof PublishedState,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Service is unavailable',
        );

        $proposedType = $safePayload['product_type'] ?? null;
        abort_if(
            $proposedType !== null && (string) $proposedType !== $service->product_type->value,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Service product type does not match the proposal',
        );

        $proposedPrice = $safePayload['unit_price_minor'] ?? null;
        abort_if(
            $proposedPrice !== null && (int) $proposedPrice !== (int) $service->base_price_minor,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Service price does not match the proposal',
        );

        $quantity = (int) ($safePayload['quantity'] ?? 1);
        abort_if($quantity < 1, Response::HTTP_UNPROCESSABLE_ENTITY, 'Quantity must be at least one');

        $booking = $bookingVendor->booking()->lockForUpdate()->firstOrFail();
        $startsAt = $safePayload['effective_starts_at'] ?? $booking->event_starts_at;
        $endsAt = $safePayload['effective_ends_at'] ?? $booking->event_ends_at;

        if ($service->product_type === ProductType::Rental) {
            abort_if(
                $startsAt === null || $endsAt === null,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Rental items require a booking window',
            );
            $this->assertRentalInventoryAvailable($service->id, $startsAt, $endsAt);
        } elseif ($service->product_type === ProductType::Sale) {
            $this->assertSaleInventoryAvailable($service->saleDetail?->stock_quantity, $service->id, $quantity);
        }

        $unitPrice = (int) $service->base_price_minor;
        $currency = (string) $service->base_price_currency;
        $itemStatus = 'pending';
        if ($service->product_type === ProductType::Rental) {
            $itemStatus = 'pending_delivery';
        }

        $item = BookingItem::create([
            'public_id' => (string) Str::ulid(),
            'booking_vendor_id' => $bookingVendor->id,
            'service_id' => $service->id,
            'product_type' => $service->product_type->value,
            'name_snapshot' => $service->getTranslations('name'),
            'unit_price_minor' => $unitPrice,
            'unit_price_currency' => $currency,
            'line_total_minor' => $unitPrice * $quantity,
            'line_total_currency' => $currency,
            'commission_minor' => 0,
            'commission_currency' => $currency,
            'quantity' => $quantity,
            'effective_starts_at' => $startsAt,
            'effective_ends_at' => $endsAt,
            'customization_data' => $safePayload['customization_data'] ?? null,
            'type_snapshot' => [
                'service_id' => $service->id,
                'service_public_id' => $service->public_id,
                'product_type' => $service->product_type->value,
                'unit_price_minor' => $unitPrice,
                'unit_price_currency' => $currency,
            ],
            'fulfillment_data' => [],
            'item_status' => $itemStatus,
            'commission_bps' => 0,
            'vat_rate_bps' => 0,
            'vat_amount_minor' => 0,
            'vat_amount_currency' => $currency,
        ]);

        $this->createInventoryReservation($service, $booking, $item, $quantity, $startsAt, $endsAt);
    }

    private function assertRentalInventoryAvailable(int $serviceId, mixed $startsAt, mixed $endsAt): void
    {
        abort_if(
            DB::table('service_inventory_reservations')
                ->where('service_id', $serviceId)
                ->where(fn ($query) => $query
                    ->where('status', 'confirmed')
                    ->orWhere(fn ($held) => $held
                        ->where('status', 'held')
                        ->where('expires_at', '>', now())))
                ->where('reserved_starts_at', '<', $endsAt)
                ->where('reserved_ends_at', '>', $startsAt)
                ->exists(),
            Response::HTTP_CONFLICT,
            'Service is unavailable for the requested dates',
        );
    }

    private function assertSaleInventoryAvailable(?int $stockQuantity, int $serviceId, int $quantity): void
    {
        if ($stockQuantity === null) {
            return;
        }

        $held = (int) DB::table('service_inventory_reservations')
            ->where('service_id', $serviceId)
            ->where(fn ($query) => $query
                ->where('status', 'confirmed')
                ->orWhere(fn ($held) => $held
                    ->where('status', 'held')
                    ->where('expires_at', '>', now())))
            ->sum('quantity');

        abort_if(
            $stockQuantity - $held < $quantity,
            Response::HTTP_CONFLICT,
            'Insufficient stock',
        );
    }

    private function createInventoryReservation(
        Service $service,
        Booking $booking,
        BookingItem $item,
        int $quantity,
        mixed $startsAt,
        mixed $endsAt,
    ): void {
        if ($service->product_type === ProductType::Digital) {
            return;
        }

        DB::table('service_inventory_reservations')->insert([
            'public_id' => (string) Str::ulid(),
            'service_id' => $service->id,
            'user_id' => $booking->customer_id,
            'product_type' => $service->product_type->value,
            'hold_type' => 'cart',
            'status' => 'held',
            'reserved_starts_at' => $startsAt,
            'reserved_ends_at' => $endsAt,
            'quantity' => $service->product_type === ProductType::Rental ? 1 : $quantity,
            'expires_at' => now()->addMinutes(15),
            'booking_item_id' => $item->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function applyRemove(BookingModificationItem $modItem): void
    {
        if ($modItem->target_booking_item_id !== null) {
            DB::table('service_inventory_reservations')
                ->where('booking_item_id', $modItem->target_booking_item_id)
                ->whereIn('status', ['held', 'confirmed'])
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => 'modification_item_removed',
                    'updated_at' => now(),
                ]);

            BookingItem::where('id', $modItem->target_booking_item_id)->delete();
        }
    }

    private function recalculateVendorSubtotal(BookingVendor $bookingVendor): void
    {
        $subtotal = BookingItem::where('booking_vendor_id', $bookingVendor->id)->sum('line_total_minor');
        $bookingVendor->update(['subtotal_minor' => $subtotal]);
    }

    private function recalculateBookingTotal(Booking $booking): void
    {
        $total = BookingVendor::where('booking_id', $booking->id)->sum('subtotal_minor');
        $booking->update(['total_minor' => $total]);
    }

    private function requestHash(CustomerModificationDecisionDTO $dto): string
    {
        return hash('sha256', 'bookings.modifications.decide|'.$dto->customerId.'|'.$dto->modificationPublicId.'|'.$dto->decision);
    }

    private function getCachedIdempotencyResponse(CustomerModificationDecisionDTO $dto): ?Booking
    {
        $row = DB::table('idempotency_keys')
            ->where('key', $dto->idempotencyKey)
            ->where('user_id', $dto->customerId)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        if (! hash_equals($row->request_hash, $this->requestHash($dto))) {
            abort(Response::HTTP_CONFLICT, 'Idempotency key conflict');
        }

        /** @var array<string,mixed> $body */
        $body = json_decode($row->response_body, true) ?? [];

        return Booking::find((int) ($body['booking_id'] ?? 0));
    }

    private function storeIdempotencyResponse(CustomerModificationDecisionDTO $dto, Booking $booking): void
    {
        DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $dto->idempotencyKey,
            'user_id' => $dto->customerId,
            'route' => 'bookings.modifications.decide',
            'request_hash' => $this->requestHash($dto),
            'response_status' => Response::HTTP_OK,
            'response_body' => json_encode(['booking_id' => $booking->id]),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);
    }
}

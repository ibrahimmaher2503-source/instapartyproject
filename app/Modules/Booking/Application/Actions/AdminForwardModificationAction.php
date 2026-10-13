<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ActiveState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Support\Facades\DB;

class AdminForwardModificationAction
{
    public function execute(BookingModification $modification, int $adminUserId): void
    {
        // Read relationship keys without taking child locks, then verify the chain again
        // as each row is locked in parent-to-child order.
        $modificationId = $modification->id;
        $bookingVendorId = BookingModification::query()
            ->whereKey($modificationId)
            ->value('booking_vendor_id');
        abort_if($bookingVendorId === null, 404);

        $bookingId = BookingVendor::query()->whereKey((int) $bookingVendorId)->value('booking_id');
        abort_if($bookingId === null, 404);

        DB::transaction(function () use ($modificationId, $bookingVendorId, $bookingId, $adminUserId): void {
            /** @var Booking $booking */
            $booking = Booking::query()
                ->whereKey((int) $bookingId)
                ->lockForUpdate()
                ->firstOrFail();

            $bookingVendors = BookingVendor::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var BookingVendor|null $bookingVendor */
            $bookingVendor = $bookingVendors->firstWhere('id', (int) $bookingVendorId);
            abort_if($bookingVendor === null, 404);

            // Match the cancellation lifecycle policy so a retained draft cannot
            // move a cancelled, started, or completed booking back to customer review.
            abort_if(
                $booking->lifecycle_status instanceof CancelledState,
                409,
                __('booking::booking.errors.booking_cancelled'),
            );

            $hasCompletedAllocation = $bookingVendors->contains(
                fn (BookingVendor $vendor): bool => $vendor->sub_status === VendorSubStatus::Completed,
            );
            abort_if(
                $booking->lifecycle_status instanceof CompletedState || $hasCompletedAllocation,
                409,
                __('booking::booking.errors.booking_completed'),
            );

            abort_if(
                $booking->lifecycle_status instanceof ActiveState,
                409,
                __('booking::booking.errors.booking_not_modifiable'),
            );

            $hasInProgressAllocation = $bookingVendors->contains(
                fn (BookingVendor $vendor): bool => $vendor->sub_status === VendorSubStatus::InProgress,
            );
            abort_if(
                $hasInProgressAllocation,
                409,
                __('booking::booking.errors.fulfillment_in_progress'),
            );

            /** @var BookingModification $modification */
            $modification = BookingModification::query()
                ->with('items')
                ->whereKey($modificationId)
                ->where('booking_vendor_id', $bookingVendor->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $modification->status !== ModificationStatus::Draft,
                409,
                'Modification is not in draft status.',
            );

            abort_if(
                $modification->items->isEmpty(),
                422,
                'Cannot forward a modification with no changes.',
            );

            $modification->update([
                'status' => ModificationStatus::Pending,
                'expires_at' => $modification->expires_at ?? now()->addHours(48),
            ]);

            StateTransition::create([
                'transitionable_type' => BookingModification::class,
                'transitionable_id' => $modification->id,
                'from_state' => ModificationStatus::Draft->value,
                'to_state' => ModificationStatus::Pending->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $adminUserId,
                'context' => ['admin_forwarded' => true],
            ]);

            $previousSubStatus = $bookingVendor->sub_status->value;
            $bookingVendor->update(['sub_status' => VendorSubStatus::Modified]);

            StateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $bookingVendor->id,
                'from_state' => $previousSubStatus,
                'to_state' => VendorSubStatus::Modified->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $adminUserId,
            ]);

            if ($booking->lifecycle_status->getValue() !== LifecycleStatus::CustomerReview->value) {
                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update(['lifecycle_status' => LifecycleStatus::CustomerReview->value]);

                StateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => $booking->lifecycle_status->getValue(),
                    'to_state' => LifecycleStatus::CustomerReview->value,
                    'trigger_kind' => 'admin',
                    'triggered_by' => $adminUserId,
                ]);
            }

            DB::afterCommit(fn () => event(new VendorModificationProposed($modification->fresh())));
        });
    }
}

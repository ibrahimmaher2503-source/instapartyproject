<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingItemFulfilled;
use App\Modules\Booking\Domain\Events\BookingVendorCompleted;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Support\Facades\DB;

/**
 * Synchronous, in-transaction listener.
 *
 * Flips BookingVendor.sub_status:
 *  - Accepted → InProgress on the first item advance.
 *  - InProgress → Completed when every item is at its per-type vendor-side terminal state.
 *
 * Idempotent: if sub_status is already at the target value, the update is a no-op.
 *
 * NOT bound as ShouldQueue — must run in the originating DB transaction so the
 * aggregate is atomic with the item transition (see research.md R-004).
 */
class AggregateBookingVendorSubStatusListener
{
    public function handle(BookingItemFulfilled $event): void
    {
        $bookingVendor = BookingVendor::query()
            ->whereKey($event->item->booking_vendor_id)
            ->lockForUpdate()
            ->first();

        if ($bookingVendor === null) {
            return;
        }

        // Reload items relation under the lock so terminal-state check sees fresh data.
        $bookingVendor->load('items');

        if (
            $bookingVendor->sub_status === VendorSubStatus::Accepted
            && $bookingVendor->hasAnyItemAdvancedFromInitial()
        ) {
            $bookingVendor->update(['sub_status' => VendorSubStatus::InProgress]);
        }

        if (
            $bookingVendor->sub_status !== VendorSubStatus::Completed
            && $bookingVendor->allItemsAtVendorTerminalState()
        ) {
            $bookingVendor->update(['sub_status' => VendorSubStatus::Completed]);

            $actor = $event->actor;
            DB::afterCommit(function () use ($bookingVendor, $actor): void {
                event(new BookingVendorCompleted($bookingVendor->refresh(), $actor));
            });
        }
    }
}

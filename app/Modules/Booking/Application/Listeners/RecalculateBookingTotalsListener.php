<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Application\Actions\RecalculateBookingTotalsAction;
use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Events\BookingItemRemoved;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Support\Facades\DB;

class RecalculateBookingTotalsListener
{
    public function __construct(private readonly RecalculateBookingTotalsAction $recalculate) {}

    public function handle(BookingItemAdded|BookingItemRemoved $event): void
    {
        if ($event instanceof BookingItemAdded) {
            $bookingVendorId = $event->item->booking_vendor_id;
            /** @var BookingVendor $vendor */
            $vendor = $event->item->bookingVendor ?? $event->item->load('bookingVendor')->bookingVendor;
            $bookingId = $vendor->booking_id;
        } else {
            $bookingVendorId = $event->bookingVendorId;
            $bookingId = $event->bookingId;
        }

        DB::transaction(function () use ($bookingId, $bookingVendorId): void {
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->first();

            if ($booking === null) {
                return;
            }

            $vendorSubtotalMinor = (int) DB::table('booking_items')
                ->where('booking_vendor_id', $bookingVendorId)
                ->sum('line_total_minor');

            DB::table('booking_vendors')
                ->where('id', $bookingVendorId)
                ->where('booking_id', $bookingId)
                ->update(['subtotal_minor' => $vendorSubtotalMinor]);

            // Keep item-driven and adjustment-driven recalculations on the same
            // currency-safe formula, including an already-applied loyalty line.
            $this->recalculate->execute($booking);
        });
    }
}

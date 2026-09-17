<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Events\BookingVendorCompleted;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ActiveState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use Illuminate\Support\Facades\DB;

final class CompleteBookingWhenAllVendorsCompletedListener
{
    public function handle(BookingVendorCompleted $event): void
    {
        DB::transaction(function () use ($event): void {
            $booking = Booking::query()
                ->whereKey($event->bookingVendor->booking_id)
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                return;
            }

            $hasIncompleteVendor = BookingVendor::query()
                ->where('booking_id', $booking->id)
                ->where('sub_status', '!=', 'completed')
                ->exists();

            if ($hasIncompleteVendor) {
                $booking->update(['fulfillment_status' => FulfillmentStatus::PartiallyCompleted]);

                return;
            }

            $booking->update(['fulfillment_status' => FulfillmentStatus::Completed]);

            if ($booking->lifecycle_status instanceof ActiveState) {
                $booking->lifecycle_status->transitionTo(CompletedState::class);
            }
        });
    }
}

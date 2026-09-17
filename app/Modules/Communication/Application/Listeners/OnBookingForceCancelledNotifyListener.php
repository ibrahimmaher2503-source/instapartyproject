<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingForceCancelled;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnBookingForceCancelledNotifyListener implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(BookingForceCancelled $event): void
    {
        $booking = $event->booking;
        $booking->loadMissing(['customer', 'vendors.vendor']);

        $reason = $event->intervention->reason;

        // Notify customer via Push + Email
        if ($booking->customer_id !== null) {
            $customerContext = [
                'booking_number' => $booking->public_id,
                'event_date' => $booking->event_starts_at?->format('Y-m-d') ?? '',
                'reason' => $reason,
                'refund_days' => '5–7',
            ];

            foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'booking.force_cancelled',
                    channel: $channel,
                    audience: NotificationAudience::Customer,
                    eventCategory: EventCategory::Booking,
                    userId: $booking->customer_id,
                    context: $customerContext,
                    referenceType: 'booking',
                    referenceId: $booking->id,
                ));
            }
        }

        // Notify engaged vendors via Push
        $engaged = [
            VendorSubStatus::Pending, VendorSubStatus::Accepted,
            VendorSubStatus::Modified, VendorSubStatus::InProgress,
        ];

        $customerName = $booking->customer?->name ?? '';

        foreach ($booking->vendors as $bookingVendor) {
            $vendorUserId = $bookingVendor->vendor?->user_id;

            if ($vendorUserId === null || ! in_array($bookingVendor->sub_status, $engaged, true)) {
                continue;
            }

            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'booking.force_cancelled.vendor',
                channel: NotificationChannel::Push,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::Booking,
                userId: $vendorUserId,
                context: [
                    'booking_number' => $booking->public_id,
                    'customer_name' => $customerName,
                    'reason' => $reason,
                ],
                referenceType: 'booking',
                referenceId: $booking->id,
            ));
        }
    }
}

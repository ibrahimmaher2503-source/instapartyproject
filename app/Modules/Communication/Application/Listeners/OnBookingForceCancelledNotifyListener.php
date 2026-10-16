<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingForceCancelled;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDetailPage;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingsPage;
use App\Modules\Communication\Application\Actions\DispatchInAppNotificationOnceAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnBookingForceCancelledNotifyListener implements ShouldQueue
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function handle(BookingForceCancelled $event): void
    {
        $booking = $event->booking;
        $booking->loadMissing(['customer', 'vendors.vendor']);

        $reason = $event->intervention->reason;

        // Notify customer via in-app + Push + Email
        if ($booking->customer_id !== null) {
            $customerContext = [
                'booking_number' => $booking->reference_no ?: $booking->public_id,
                'event_date' => $booking->event_starts_at?->format('Y-m-d') ?? '',
                'reason' => $reason,
                'refund_days' => '5–7',
                'action_url' => route('storefront.account.bookings.show', [
                    'locale' => $booking->customer?->preferred_locale === 'ar' ? 'ar' : 'en',
                    'publicId' => $booking->public_id,
                ]),
            ];

            foreach ([NotificationChannel::InApp, NotificationChannel::Push, NotificationChannel::Email] as $channel) {
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

        // A non-null list is captured before the booking-vendor statuses changed.
        // Null is retained for compatibility with events queued by older producers.
        $engaged = [
            VendorSubStatus::Pending, VendorSubStatus::Accepted,
            VendorSubStatus::Modified, VendorSubStatus::InProgress,
        ];
        $customerName = $booking->customer?->name ?? '';

        $vendorUserIds = $event->engagedVendorUserIds;

        if ($vendorUserIds === null) {
            $vendorUserIds = $booking->vendors
                ->filter(fn ($bookingVendor): bool => in_array($bookingVendor->sub_status, $engaged, true))
                ->map(fn ($bookingVendor) => $bookingVendor->vendor?->user_id)
                ->filter(fn ($userId): bool => $userId !== null)
                ->map(fn ($userId): int => (int) $userId)
                ->unique()
                ->values()
                ->all();
        }

        // Notify engaged vendors in-app and via Push. Captured empty lists stay empty so
        // current terminal rows are never re-added after cancellation.
        foreach (array_unique(array_filter($vendorUserIds, fn ($userId): bool => $userId !== null)) as $vendorUserId) {
            $bookingVendor = $booking->vendors->first(fn ($row): bool => $row->vendor?->user_id === $vendorUserId);
            $vendorUser = $bookingVendor?->vendor?->user;
            $locale = $vendorUser?->preferred_locale === 'ar' ? 'ar' : 'en';
            app(DispatchInAppNotificationOnceAction::class)->execute(new DispatchNotificationDTO(
                eventKey: 'booking.force_cancelled.vendor',
                channel: NotificationChannel::InApp,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::Booking,
                userId: $vendorUserId,
                context: [
                    'booking_number' => $booking->reference_no ?: $booking->public_id,
                    'notification_format' => 'filament',
                    'action_label' => __('vendor-portal.bookings.view_details', [], $locale),
                    'action_url' => $bookingVendor !== null
                        ? VendorBookingDetailPage::getUrl(['bookingVendor' => $bookingVendor->public_id], panel: 'vendor')
                        : VendorBookingsPage::getUrl(panel: 'vendor'),
                ],
                referenceType: 'booking',
                referenceId: $booking->id,
            ), 'vendor_force_cancelled:'.$event->intervention->public_id);

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

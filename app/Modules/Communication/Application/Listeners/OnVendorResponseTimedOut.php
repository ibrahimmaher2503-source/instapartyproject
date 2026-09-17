<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorResponseTimedOut;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnVendorResponseTimedOut implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
        private readonly RouteToAdminInboxAction $router,
    ) {}

    public function handle(VendorResponseTimedOut $event): void
    {
        $booking = Booking::query()->find($event->bookingId);
        if ($booking === null) {
            return;
        }

        $booking->loadMissing(['vendors.vendor']);

        $bookingNumber = $booking->public_id;
        $eventDate = $booking->event_starts_at?->format('Y-m-d') ?? '';

        // Notify customer
        if ($booking->customer_id !== null) {
            foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'booking.vendor_timeout',
                    channel: $channel,
                    audience: NotificationAudience::Customer,
                    eventCategory: EventCategory::Booking,
                    userId: $booking->customer_id,
                    context: [
                        'booking_number' => $bookingNumber,
                        'event_date' => $eventDate,
                    ],
                    referenceType: 'booking',
                    referenceId: $booking->id,
                ));
            }
        }

        // Admin inbox
        $timedOutVendors = $booking->vendors
            ->whereIn('id', $event->bookingVendorIds)
            ->filter(fn ($bv) => $bv->sub_status === VendorSubStatus::TimedOut)
            ->map(function ($bookingVendor): string {
                $vendor = $bookingVendor->vendor;

                return $vendor instanceof VendorProfile
                    ? $vendor->getTranslation('business_name', 'en')
                    : "Vendor #{$bookingVendor->vendor_profile_id}";
            })
            ->implode(', ');

        $this->router->execute(
            eventKey: 'booking.vendor_response_timeout',
            severity: AdminInboxSeverity::Critical,
            sourceType: 'booking',
            sourceId: $booking->id,
            title: [
                'en' => "[TIMEOUT] Booking #{$bookingNumber} — vendor(s) did not respond",
                'ar' => "[انتهاء المهلة] الحجز #{$bookingNumber} — لم يستجب المورد",
            ],
            body: [
                'en' => "Booking #{$bookingNumber} (event {$eventDate}): {$timedOutVendors} did not respond within the 24h SLA. Manual intervention required.",
                'ar' => "الحجز #{$bookingNumber} (الحدث {$eventDate}): {$timedOutVendors} لم يستجب خلال 24 ساعة. يلزم التدخل اليدوي.",
            ],
        );
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }
}

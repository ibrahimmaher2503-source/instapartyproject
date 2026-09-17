<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnVendorModificationProposed implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorModificationProposed $event): void
    {
        $modification = $event->modification;
        $modification->loadMissing(['bookingVendor.booking', 'bookingVendor.vendor']);

        $bookingVendor = $modification->bookingVendor;
        $booking = $bookingVendor?->booking;
        $customerId = $booking?->customer_id;

        if ($customerId === null || $booking === null) {
            return;
        }

        $context = [
            'booking_number' => $booking->public_id,
            'vendor_name' => $bookingVendor->vendor?->getTranslation('business_name', 'en') ?? '',
            'deadline_at' => $modification->expires_at?->format('Y-m-d H:i') ?? '',
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'booking.modification_proposed',
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::Booking,
                userId: $customerId,
                context: $context,
                referenceType: 'booking',
                referenceId: $booking->id,
            ));
        }
    }
}

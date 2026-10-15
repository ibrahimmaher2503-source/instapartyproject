<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;

final class OnBookingSubmittedInbox
{
    public function __construct(private readonly RouteToAdminInboxAction $router) {}

    public function handle(BookingSubmittedToVendor $event): void
    {
        if ($event->bookingVendor->refresh()->sub_status !== VendorSubStatus::Pending) {
            return;
        }

        $booking = $event->booking;
        $publicId = $booking->public_id;

        $this->router->execute(
            eventKey: 'booking.vendor_request.pending',
            severity: AdminInboxSeverity::Warning,
            sourceType: 'booking',
            sourceId: $booking->id,
            title: ['en' => "Vendor response needed for booking #{$publicId}", 'ar' => "يلزم الرد على طلب المورد للحجز #{$publicId}"],
            body: [
                'en' => 'Review the pending vendor request and record an administrative decision with a reason.',
                'ar' => 'راجع طلب المورد المعلق وسجّل قراراً إدارياً مع ذكر السبب.',
            ],
        );
    }
}

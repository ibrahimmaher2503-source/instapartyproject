<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

class OnBookingSubmitted implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(BookingSubmittedToVendor $event): void
    {
        $booking = $event->booking;
        $bookingVendor = $event->bookingVendor;
        $vendor = $bookingVendor->vendor;
        $customer = $booking->customer;

        $customerName = is_array($customer?->name)
            ? ($customer->name['en'] ?? '')
            : (string) ($customer?->name ?? '');

        $vendorName = is_array($vendor?->business_name)
            ? ($vendor->business_name['en'] ?? '')
            : (string) ($vendor?->business_name ?? '');

        $context = [
            'booking_id' => $booking->public_id,
            'customer_name' => $customerName,
            'vendor_name' => $vendorName,
            'event_date' => $booking->event_starts_at?->format('Y-m-d') ?? '',
        ];

        $this->createAdminInboxItems($booking->id, $bookingVendor, $vendor);

        // Customer: in-app + push + email
        if ($booking->customer_id !== null) {
            foreach ([NotificationChannel::InApp, NotificationChannel::Push, NotificationChannel::Email] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'booking.submitted',
                    channel: $channel,
                    audience: NotificationAudience::Customer,
                    eventCategory: EventCategory::Booking,
                    userId: $booking->customer_id,
                    context: $context,
                    referenceType: 'booking',
                    referenceId: $booking->id,
                ));
            }
        }

        // Vendor: push + sms
        $vendorUserId = $vendor?->user_id;
        if ($vendorUserId !== null) {
            foreach ([NotificationChannel::Push, NotificationChannel::Sms] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'booking.submitted',
                    channel: $channel,
                    audience: NotificationAudience::Vendor,
                    eventCategory: EventCategory::Booking,
                    userId: $vendorUserId,
                    context: $context,
                    referenceType: 'booking',
                    referenceId: $booking->id,
                ));
            }
        }
    }

    private function createAdminInboxItems(int $bookingId, BookingVendor $bookingVendor, ?VendorProfile $vendor): void
    {
        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'super_admin', 'booking_manager']))
            ->pluck('id');

        $reference = $bookingVendor->booking?->reference_no ?? $bookingVendor->booking_id;
        $deadline = $bookingVendor->response_deadline?->toIso8601String() ?? '—';
        $businessName = $vendor?->getTranslations('business_name') ?? [];
        $vendorNameEn = trim(is_array($businessName) ? (string) ($businessName['en'] ?? '') : (string) $businessName);
        $vendorNameAr = trim(is_array($businessName) ? (string) ($businessName['ar'] ?? '') : (string) $businessName);
        $vendorNameEn = $vendorNameEn !== '' ? $vendorNameEn : 'Vendor';
        $vendorNameAr = $vendorNameAr !== '' ? $vendorNameAr : 'مقدم الخدمة';

        foreach ($admins as $adminId) {
            AdminInboxItem::firstOrCreate(
                ['source_type' => 'booking', 'source_id' => $bookingId, 'admin_id' => $adminId],
                [
                    'public_id' => Str::ulid()->toBase32(),
                    'severity' => AdminInboxSeverity::Warning,
                    'status' => 'unread',
                    'title' => [
                        'en' => "Booking #{$reference} submitted",
                        'ar' => "تم استلام الحجز رقم {$reference}",
                    ],
                    'body' => [
                        'en' => "Awaiting response from {$vendorNameEn} by {$deadline}.",
                        'ar' => "بانتظار رد {$vendorNameAr} قبل {$deadline}.",
                    ],
                ],
            );
        }
    }
}

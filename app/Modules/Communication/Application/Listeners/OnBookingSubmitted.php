<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDecisionPage;
use App\Modules\Communication\Application\Actions\DispatchInAppNotificationOnceAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class OnBookingSubmitted implements ShouldQueue
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
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

        // Customer: in-app + push + email
        if ($booking->customer_id !== null) {
            $locale = $customer?->preferred_locale === 'ar' ? 'ar' : 'en';

            DB::transaction(function () use ($booking, $context, $locale): void {
                if (DB::table('users')->where('id', $booking->customer_id)->lockForUpdate()->first(['id']) === null) {
                    return;
                }

                foreach ([NotificationChannel::InApp, NotificationChannel::Push, NotificationChannel::Email] as $channel) {
                    $eventIdentity = implode(':', [
                        'booking_submitted',
                        'customer',
                        (string) $booking->customer_id,
                        $booking->public_id,
                        $channel->value,
                    ]);

                    $alreadyDispatched = NotificationDispatch::query()
                        ->where('user_id', $booking->customer_id)
                        ->where('channel', $channel->value)
                        ->where('reference_type', 'booking')
                        ->where('reference_id', $booking->id)
                        ->where(function ($query) use ($eventIdentity): void {
                            $query->where('context->event_identity', $eventIdentity)
                                ->orWhere(function ($query): void {
                                    $query->where('context->event_identity', 'booking_submitted_customer')
                                        ->orWhere(function ($query): void {
                                            $query->where('context->event_key', 'booking.submitted')
                                                ->where('context->audience', NotificationAudience::Customer->value);
                                        });
                                })
                                ->orWhereHas('template', fn ($query) => $query
                                    ->where('event_key', 'booking.submitted')
                                    ->where('audience', NotificationAudience::Customer->value));
                        })
                        ->exists();

                    if ($alreadyDispatched) {
                        continue;
                    }

                    $customerContext = [
                        ...$context,
                        'booking_number' => $booking->reference_no ?: $booking->public_id,
                        'booking_status' => __('booking::booking.lifecycle_status.'.$booking->lifecycle_status->getValue(), [], $locale),
                        'action_url' => route('storefront.account.bookings.show', [
                            'locale' => $locale,
                            'publicId' => $booking->public_id,
                        ]),
                        'event_identity' => $eventIdentity,
                        'event_key' => 'booking.submitted',
                        'audience' => NotificationAudience::Customer->value,
                    ];

                    $this->dispatcher->execute(new DispatchNotificationDTO(
                        eventKey: 'booking.submitted',
                        channel: $channel,
                        audience: NotificationAudience::Customer,
                        eventCategory: EventCategory::Booking,
                        userId: $booking->customer_id,
                        context: $customerContext,
                        referenceType: 'booking',
                        referenceId: $booking->id,
                    ));
                }
            });
        }

        // Vendor: actionable in-app request + existing push and SMS channels.
        $vendorUserId = $vendor?->user_id;
        if ($vendorUserId !== null) {
            if ($bookingVendor->refresh()->sub_status === VendorSubStatus::Pending) {
                $locale = $vendor->user?->preferred_locale === 'ar' ? 'ar' : 'en';
                app(DispatchInAppNotificationOnceAction::class)->execute(new DispatchNotificationDTO(
                    eventKey: 'booking.submitted',
                    channel: NotificationChannel::InApp,
                    audience: NotificationAudience::Vendor,
                    eventCategory: EventCategory::Booking,
                    userId: $vendorUserId,
                    context: [
                        ...$context,
                        'booking_number' => $booking->reference_no ?: $booking->public_id,
                        'booking_vendor_public_id' => $bookingVendor->public_id,
                        'response_deadline' => $bookingVendor->response_deadline?->format('Y-m-d H:i \U\T\C') ?? '—',
                        'notification_format' => 'filament',
                        'action_label' => __('vendor-portal.decision.title', [], $locale),
                        'action_url' => VendorBookingDecisionPage::getUrl(['bookingVendor' => $bookingVendor->public_id], panel: 'vendor'),
                    ],
                    referenceType: 'booking',
                    referenceId: $booking->id,
                ), 'vendor_request:'.$bookingVendor->public_id);
            }

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
}

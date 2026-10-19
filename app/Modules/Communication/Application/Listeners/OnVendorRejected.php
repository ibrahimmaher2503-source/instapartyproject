<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Communication\Application\Actions\DispatchInAppNotificationOnceAction;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Application\Services\TemplateNotFoundException;
use App\Modules\Communication\Application\Services\TemplateResolver;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use InvalidArgumentException;

class OnVendorRejected implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorRejected $event): void
    {
        $bookingVendor = $event->bookingVendor;
        $booking = $bookingVendor->booking;

        if ($booking === null || $booking->customer_id === null) {
            return;
        }

        $vendor = $bookingVendor->vendor;
        $vendorName = $this->localizedValues($vendor?->getTranslations('business_name') ?? $vendor?->business_name);
        $reason = $this->localizedValues($bookingVendor->rejection_reason);

        $context = [
            'event_identity' => (string) $bookingVendor->public_id,
            'booking_id' => $booking->public_id,
            'vendor_name' => $vendorName['en'],
            'rejection_reason' => $reason['en'],
        ];

        try {
            $resolver = app(TemplateResolver::class);
            foreach (['en', 'ar'] as $locale) {
                $rendered = $resolver->resolve(
                    'booking.rejected',
                    NotificationChannel::InApp,
                    NotificationAudience::Customer,
                    $locale,
                )->render([
                    ...$context,
                    'vendor_name' => $vendorName[$locale],
                    'rejection_reason' => $reason[$locale],
                ]);
                $context['localized_content'][$locale] = [
                    'title' => $rendered['subject'],
                    'body' => $rendered['body'],
                ];
            }
        } catch (TemplateNotFoundException|InvalidArgumentException) {
            // Keep the normal dispatch path and its historical rendered content when a template is unavailable.
        }

        foreach ([NotificationChannel::InApp, NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $dispatcher = $channel === NotificationChannel::InApp
                ? app(DispatchInAppNotificationOnceAction::class)
                : $this->dispatcher;
            $dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'booking.rejected',
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

    /** @return array{en: string, ar: string} */
    private function localizedValues(mixed $value): array
    {
        $values = is_array($value) ? $value : ['en' => (string) ($value ?? '')];
        $english = (string) ($values['en'] ?? $values['ar'] ?? '');
        $arabic = (string) ($values['ar'] ?? $values['en'] ?? '');

        return ['en' => $english, 'ar' => $arabic];
    }
}

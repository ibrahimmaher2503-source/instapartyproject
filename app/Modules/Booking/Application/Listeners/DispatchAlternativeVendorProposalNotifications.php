<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Booking\Domain\Events\AlternativeVendorProposalCreated;
use App\Modules\Booking\Domain\Events\AlternativeVendorProposalDecided;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingsPage;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Contracts\NotificationDispatcher;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Support\Facades\DB;

final class DispatchAlternativeVendorProposalNotifications
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(AlternativeVendorProposalCreated|AlternativeVendorProposalDecided $event): void
    {
        if ($event instanceof AlternativeVendorProposalCreated) {
            $this->created($event);

            return;
        }

        $this->decided($event);
    }

    public function created(AlternativeVendorProposalCreated $event): void
    {
        $booking = Booking::query()->find($event->bookingId);
        $proposal = BookingAdminIntervention::query()->find($event->interventionId);

        if ($booking === null || $proposal === null) {
            return;
        }

        $offer = (array) data_get($proposal->after_state, 'offer', []);
        $actionUrl = route('storefront.account.bookings.show', [
            'locale' => app()->getLocale(),
            'publicId' => $booking->public_id,
        ]);
        $context = [
            'booking_reference' => $booking->reference_no,
            'proposal_public_id' => $proposal->public_id,
            'vendor_name' => (string) (data_get($offer, 'candidate_vendor.business_name.'.app()->getLocale())
                ?: data_get($offer, 'candidate_vendor.business_name.en', '')),
            'action_url' => $actionUrl,
        ];

        $this->dispatchOnce(
            'booking.alternative_vendor.proposed',
            $booking->customer_id,
            NotificationAudience::Customer,
            $proposal,
            $context,
            'proposed:customer',
        );
    }

    public function decided(AlternativeVendorProposalDecided $event): void
    {
        $booking = Booking::query()->find($event->bookingId);
        $proposal = BookingAdminIntervention::query()->find($event->interventionId);

        if ($booking === null || $proposal === null) {
            return;
        }

        $context = [
            'booking_reference' => $booking->reference_no,
            'proposal_public_id' => $proposal->public_id,
            'decision' => $event->decision,
            'vendor_name' => (string) (data_get($proposal->after_state, 'offer.candidate_vendor.business_name.'.app()->getLocale())
                ?: data_get($proposal->after_state, 'offer.candidate_vendor.business_name.en', '')),
            'action_url' => route('storefront.account.bookings.show', [
                'locale' => app()->getLocale(),
                'publicId' => $booking->public_id,
            ]),
        ];

        $this->dispatchOnce(
            'booking.alternative_vendor.decided',
            $booking->customer_id,
            NotificationAudience::Customer,
            $proposal,
            $context,
            'decided:customer:'.$event->decision,
        );

        foreach (array_unique([$event->previousVendorProfileId, $event->newVendorProfileId]) as $vendorProfileId) {
            if ($vendorProfileId === null) {
                continue;
            }
            $vendorAllocation = BookingVendor::query()
                ->where('booking_id', $booking->getKey())
                ->where('vendor_profile_id', $vendorProfileId)
                ->first();
            $vendorProfile = DB::table('vendor_profiles')->where('id', $vendorProfileId)->first(['user_id', 'business_name']);
            $userId = $vendorAllocation?->vendor?->user?->getAuthIdentifier() ?? $vendorProfile?->user_id;

            if ($userId === null) {
                continue;
            }

            $this->dispatchOnce(
                'booking.alternative_vendor.decided',
                (int) $userId,
                NotificationAudience::Vendor,
                $proposal,
                [...$context, 'vendor_name' => $this->localizedName($vendorProfile?->business_name), 'action_url' => VendorBookingsPage::getUrl(panel: 'vendor')],
                'decided:vendor:'.$vendorProfileId.':'.$event->decision,
            );
        }
    }

    /** @param array<string, scalar|null> $context */
    private function dispatchOnce(
        string $eventKey,
        int $userId,
        NotificationAudience $audience,
        BookingAdminIntervention $proposal,
        array $context,
        string $identity,
    ): void {
        $eventIdentity = $proposal->public_id.':'.$identity;

        DB::transaction(function () use ($eventKey, $userId, $audience, $proposal, $context, $eventIdentity): void {
            if (DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id']) === null) {
                return;
            }

            $exists = DB::table('notification_dispatches')
                ->where('user_id', $userId)
                ->where('channel', NotificationChannel::InApp->value)
                ->where('reference_type', BookingAdminIntervention::class)
                ->where('reference_id', $proposal->getKey())
                ->where('context->event_identity', $eventIdentity)
                ->exists();

            if ($exists) {
                return;
            }

            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: $eventKey,
                channel: NotificationChannel::InApp,
                audience: $audience,
                eventCategory: EventCategory::Booking,
                userId: $userId,
                context: [...$context, 'event_identity' => $eventIdentity],
                referenceType: BookingAdminIntervention::class,
                referenceId: (int) $proposal->getKey(),
            ));
        });
    }

    private function localizedName(mixed $jsonName): string
    {
        $name = is_array($jsonName) ? $jsonName : json_decode((string) $jsonName, true);
        if (! is_array($name)) {
            return '';
        }

        return (string) ($name[app()->getLocale()] ?? $name['en'] ?? '');
    }
}

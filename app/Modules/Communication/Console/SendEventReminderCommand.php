<?php

declare(strict_types=1);

namespace App\Modules\Communication\Console;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendEventReminderCommand extends Command
{
    protected $signature = 'communication:send-event-reminders';

    protected $description = 'Send event-day reminders to customers and vendors for bookings happening within the next 24 hours.';

    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = now()->addHours(20);
        $to = now()->addHours(28);

        $bookings = DB::table('bookings')
            ->leftJoin('booking_addresses', 'booking_addresses.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.lifecycle_status', ['confirmed', 'active'])
            ->whereBetween('bookings.event_starts_at', [$from, $to])
            ->select([
                'bookings.id',
                'bookings.public_id',
                'bookings.customer_id',
                'bookings.event_starts_at',
                'booking_addresses.address_line',
            ])
            ->get();

        $dispatched = 0;

        foreach ($bookings as $booking) {
            $eventDate = $booking->event_starts_at
                ? date('Y-m-d', strtotime($booking->event_starts_at))
                : '';

            $location = $this->resolveLocation($booking->address_line);

            $vendorRows = DB::table('booking_vendors')
                ->join('vendor_profiles', 'vendor_profiles.id', '=', 'booking_vendors.vendor_profile_id')
                ->where('booking_vendors.booking_id', $booking->id)
                ->whereIn('booking_vendors.sub_status', ['accepted', 'in_progress'])
                ->select(['vendor_profiles.user_id'])
                ->get();

            $vendorCount = $vendorRows->count();

            // Customer — Push, Email, SMS
            if ($booking->customer_id !== null) {
                $customerCtx = [
                    'booking_number' => $booking->public_id,
                    'event_date' => $eventDate,
                    'event_location' => $location,
                    'vendor_count' => (string) $vendorCount,
                ];

                foreach ([NotificationChannel::Push, NotificationChannel::Email, NotificationChannel::Sms] as $channel) {
                    $this->dispatcher->execute(new DispatchNotificationDTO(
                        eventKey: 'booking.event_reminder',
                        channel: $channel,
                        audience: NotificationAudience::Customer,
                        eventCategory: EventCategory::Booking,
                        userId: $booking->customer_id,
                        context: $customerCtx,
                        referenceType: 'booking',
                        referenceId: $booking->id,
                    ));
                }

                $dispatched++;
            }

            // Vendors — Push, SMS
            $vendorCtx = [
                'booking_number' => $booking->public_id,
                'event_date' => $eventDate,
            ];

            foreach ($vendorRows as $vendorRow) {
                if ($vendorRow->user_id === null) {
                    continue;
                }

                foreach ([NotificationChannel::Push, NotificationChannel::Sms] as $channel) {
                    $this->dispatcher->execute(new DispatchNotificationDTO(
                        eventKey: 'booking.event_reminder',
                        channel: $channel,
                        audience: NotificationAudience::Vendor,
                        eventCategory: EventCategory::Booking,
                        userId: $vendorRow->user_id,
                        context: $vendorCtx,
                        referenceType: 'booking',
                        referenceId: $booking->id,
                    ));
                }
            }
        }

        $this->info("Event reminders sent for {$dispatched} booking(s).");

        return self::SUCCESS;
    }

    private function resolveLocation(mixed $addressLine): string
    {
        if ($addressLine === null) {
            return '';
        }

        if (is_string($addressLine)) {
            $decoded = json_decode($addressLine, true);
            if (is_array($decoded)) {
                return $decoded['en'] ?? $decoded[array_key_first($decoded)] ?? '';
            }

            return $addressLine;
        }

        return '';
    }
}

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

class SendVendorDeadlineReminderCommand extends Command
{
    protected $signature = 'communication:send-vendor-deadline-reminders';

    protected $description = 'Send a 4-hour deadline reminder to vendors who have not yet responded to a booking request.';

    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Target vendors whose response_deadline falls in the 3.5h–4.5h window from now.
        // ±30 min around the 4h mark prevents double-firing between runs.
        $from = now()->addMinutes(210); // 3h 30m
        $to = now()->addMinutes(270);   // 4h 30m

        $rows = DB::table('booking_vendors')
            ->join('bookings', 'bookings.id', '=', 'booking_vendors.booking_id')
            ->join('vendor_profiles', 'vendor_profiles.id', '=', 'booking_vendors.vendor_profile_id')
            ->join('users', 'users.id', '=', 'vendor_profiles.user_id')
            ->leftJoin('users as customers', 'customers.id', '=', 'bookings.customer_id')
            ->where('booking_vendors.sub_status', 'pending')
            ->whereBetween('booking_vendors.response_deadline', [$from, $to])
            ->select([
                'booking_vendors.id',
                'booking_vendors.booking_id',
                'booking_vendors.response_deadline',
                'bookings.public_id as booking_number',
                'vendor_profiles.user_id',
                'customers.name as customer_name',
            ])
            ->get();

        foreach ($rows as $row) {
            if ($row->user_id === null) {
                continue;
            }

            $context = [
                'booking_number' => $row->booking_number,
                'customer_name' => $row->customer_name ?? '',
                'hours' => '4',
                'deadline_at' => date('Y-m-d H:i', strtotime($row->response_deadline)),
            ];

            foreach ([NotificationChannel::Push, NotificationChannel::Sms] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'booking.vendor_deadline_reminder',
                    channel: $channel,
                    audience: NotificationAudience::Vendor,
                    eventCategory: EventCategory::Booking,
                    userId: $row->user_id,
                    context: $context,
                    referenceType: 'booking',
                    referenceId: $row->booking_id,
                ));
            }
        }

        $this->info("Deadline reminders sent to {$rows->count()} vendor(s).");

        return self::SUCCESS;
    }
}

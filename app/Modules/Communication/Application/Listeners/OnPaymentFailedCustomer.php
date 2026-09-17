<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class OnPaymentFailedCustomer implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(PaymentFailed $event): void
    {
        $booking = DB::table('bookings')
            ->where('id', $event->bookingId)
            ->select(['public_id', 'customer_id'])
            ->first();

        if ($booking === null || $booking->customer_id === null) {
            return;
        }

        $context = [
            'booking_number' => $booking->public_id,
            'failure_reason' => $event->failureMessage['en'] ?? $event->failureCode,
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'payment.failed.customer',
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::Payment,
                userId: $booking->customer_id,
                context: $context,
                referenceType: 'booking',
                referenceId: $event->bookingId,
            ));
        }
    }
}

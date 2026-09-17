<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Payments\Domain\Events\RefundFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class OnRefundFailed implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
        private readonly RouteToAdminInboxAction $router,
    ) {}

    public function handle(RefundFailed $event): void
    {
        $refund = DB::table('refunds')
            ->where('id', $event->refundId)
            ->select(['booking_id'])
            ->first();

        $bookingId = $refund?->booking_id;

        $booking = $bookingId !== null
            ? DB::table('bookings')->where('id', $bookingId)->select(['public_id', 'customer_id'])->first()
            : null;

        $failureReason = $event->failureMessage['en'] ?? 'processing error';
        $bookingNumber = $booking?->public_id ?? "payment #{$event->paymentId}";

        // Notify customer
        if ($booking !== null && $booking->customer_id !== null) {
            foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
                $this->dispatcher->execute(new DispatchNotificationDTO(
                    eventKey: 'refund.failed.customer',
                    channel: $channel,
                    audience: NotificationAudience::Customer,
                    eventCategory: EventCategory::Payment,
                    userId: $booking->customer_id,
                    context: [
                        'booking_number' => $bookingNumber,
                        'failure_reason' => $failureReason,
                    ],
                    referenceType: 'booking',
                    referenceId: $bookingId ?? 0,
                ));
            }
        }

        // Admin inbox
        $this->router->execute(
            eventKey: 'refund.failed',
            severity: AdminInboxSeverity::Critical,
            sourceType: 'refund',
            sourceId: $event->refundId,
            title: [
                'en' => "[REFUND FAILED] Booking #{$bookingNumber}",
                'ar' => "[فشل الاسترداد] الحجز #{$bookingNumber}",
            ],
            body: [
                'en' => "Refund #{$event->refundId} for booking #{$bookingNumber} failed: {$failureReason}. Manual resolution required.",
                'ar' => "فشل الاسترداد #{$event->refundId} للحجز #{$bookingNumber}: {$failureReason}. يلزم الحل اليدوي.",
            ],
        );
    }
}

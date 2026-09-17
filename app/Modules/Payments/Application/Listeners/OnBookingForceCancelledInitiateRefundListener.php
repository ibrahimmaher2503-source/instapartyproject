<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingForceCancelled;
use App\Modules\Payments\Application\Actions\InitiateRefundAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Contracts\RefundPolicyResolver;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\CapturedState;

class OnBookingForceCancelledInitiateRefundListener
{
    public function __construct(
        private readonly RefundPolicyResolver $refundPolicy,
        private readonly InitiateRefundAction $initiateRefund,
    ) {}

    public function handle(BookingForceCancelled $event): void
    {
        $booking = $event->booking;

        if ((int) ($booking->amount_paid_minor ?? 0) <= 0) {
            return;
        }

        $booking->loadMissing('vendors.items');

        foreach ($booking->vendors as $bookingVendor) {
            foreach ($bookingVendor->items as $item) {
                $policy = $this->refundPolicy->policyFor(
                    $item->product_type,
                    (string) $item->item_status,
                    $booking->event_starts_at,
                    (int) $item->service_id,
                );

                // Mixed/non-refundable items remain in the admin manual-review path.
                if (! $policy->allowed) {
                    return;
                }
            }
        }

        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->whereState('status', CapturedState::class)
            ->latest('id')
            ->first();

        if ($payment === null) {
            return;
        }

        $this->initiateRefund->execute(new InitiateRefundDto(
            paymentId: (int) $payment->id,
            bookingId: (int) $booking->id,
            initiatedBy: (int) $event->intervention->admin_id,
            reasonCode: RefundReasonCode::AdminDiscretion,
            reasonNotes: [
                'en' => 'Booking force-cancelled by an administrator.',
                'ar' => 'تم إلغاء الحجز قسريًا بواسطة أحد المشرفين.',
            ],
        ));
    }
}

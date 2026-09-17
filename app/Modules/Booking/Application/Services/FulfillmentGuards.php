<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Services;

use App\Modules\Booking\Domain\Exceptions\BookingNotEligibleForFulfillmentException;
use App\Modules\Booking\Domain\Exceptions\PaymentNotCapturedException;
use App\Modules\Booking\Domain\Exceptions\RefundInProgressException;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PaidState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PartiallyRefundedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\RefundedState;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Refund;
use Illuminate\Http\Response;

/**
 * Centralised guard chain for vendor fulfillment Actions (FR-EXT-040-019).
 *
 * Real state-class mapping (per current codebase, spec 026):
 * - "payment captured" → BookingPaymentStatus\PaidState
 * - "booking refunded" → BookingPaymentStatus\RefundedState | PartiallyRefundedState
 * - "booking cancelled" → BookingLifecycleStatus\CancelledState
 */
class FulfillmentGuards
{
    public function ensureOwningVendor(BookingVendor $bookingVendor, VendorProfile $vendor): void
    {
        abort_if(
            $bookingVendor->vendor_profile_id !== $vendor->id,
            Response::HTTP_FORBIDDEN,
            'fulfillment.not_your_booking'
        );
    }

    public function ensurePaymentCaptured(BookingVendor $bookingVendor): void
    {
        $booking = $bookingVendor->booking ?? $bookingVendor->booking()->first();

        if ($booking === null || ! $booking->payment_status instanceof PaidState) {
            throw new PaymentNotCapturedException;
        }
    }

    public function ensureBookingEligible(BookingVendor $bookingVendor): void
    {
        $booking = $bookingVendor->booking ?? $bookingVendor->booking()->first();

        if ($booking === null) {
            throw BookingNotEligibleForFulfillmentException::cancelled();
        }

        if ($booking->lifecycle_status instanceof CancelledState) {
            throw BookingNotEligibleForFulfillmentException::cancelled();
        }

        if (
            $booking->payment_status instanceof RefundedState
            || $booking->payment_status instanceof PartiallyRefundedState
        ) {
            throw BookingNotEligibleForFulfillmentException::refunded();
        }
    }

    public function ensureNoOpenRefund(BookingVendor $bookingVendor): void
    {
        $booking = $bookingVendor->booking ?? $bookingVendor->booking()->first();

        if ($booking === null) {
            return;
        }

        $hasOpenRefund = Refund::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', [RefundStatus::Pending, RefundStatus::Processing])
            ->exists();

        if ($hasOpenRefund) {
            throw new RefundInProgressException;
        }
    }

    /**
     * Run all four pre-state-machine guards in spec order (FR-EXT-040-019 steps 1-4).
     */
    public function runAll(BookingVendor $bookingVendor, VendorProfile $vendor): void
    {
        $this->ensureOwningVendor($bookingVendor, $vendor);
        $this->ensureBookingEligible($bookingVendor);
        $this->ensurePaymentCaptured($bookingVendor);
        $this->ensureNoOpenRefund($bookingVendor);
    }
}

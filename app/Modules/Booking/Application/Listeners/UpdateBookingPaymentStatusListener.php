<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class UpdateBookingPaymentStatusListener implements ShouldQueue
{
    public function handle(PaymentCaptured $event): void
    {
        // Rebuild from payment/refund rows so a retried PaymentCaptured event
        // cannot increment the booking twice. Payment rows remain the source
        // of truth; the booking column is only a read-optimized projection.
        $capturedMinor = (int) DB::table('payments')
            ->where('booking_id', $event->bookingId)
            ->whereIn('status', ['captured', 'refunded', 'partially_refunded'])
            ->sum('amount_minor');
        $refundedMinor = (int) DB::table('refunds')
            ->where('booking_id', $event->bookingId)
            ->where('status', 'completed')
            ->sum('amount_minor');

        DB::table('bookings')->where('id', $event->bookingId)->update([
            'payment_status' => 'paid',
            'amount_paid_minor' => max(0, $capturedMinor - $refundedMinor),
            'amount_paid_currency' => $event->amountCurrency,
            'updated_at' => now(),
        ]);
    }

    public function handleRefund(RefundCompleted $event): void
    {
        // CASE (not GREATEST) — portable across MySQL (prod) and SQLite (tests).
        $delta = (int) $event->amountMinor;
        DB::table('bookings')->where('id', $event->bookingId)->update([
            'payment_status' => 'refunded',
            'amount_paid_minor' => DB::raw("CASE WHEN amount_paid_minor - {$delta} < 0 THEN 0 ELSE amount_paid_minor - {$delta} END"),
            'updated_at' => now(),
        ]);
    }
}

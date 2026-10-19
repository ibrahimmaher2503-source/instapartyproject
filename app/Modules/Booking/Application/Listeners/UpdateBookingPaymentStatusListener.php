<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Domain\Contracts\BookingPaymentTotalsReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class UpdateBookingPaymentStatusListener implements ShouldQueue
{
    public function __construct(private readonly BookingPaymentTotalsReader $totals) {}

    public function handle(PaymentCaptured $event): void
    {
        $this->project($event->bookingId);
    }

    public function handleRefund(RefundCompleted $event): void
    {
        $this->project($event->bookingId);
    }

    private function project(int $bookingId): void
    {
        DB::transaction(function () use ($bookingId): void {
            $booking = DB::table('bookings')->where('id', $bookingId)->lockForUpdate()->first();
            if ($booking === null) {
                return;
            }

            // Rebuild both event paths; retries and delayed captures cannot erase a refund.
            $totals = $this->totals->forBooking($bookingId, (string) $booking->total_currency);
            $status = match (true) {
                $totals['refunded_minor'] > 0 && $totals['net_paid_minor'] === 0 => 'refunded',
                $totals['refunded_minor'] > 0 => 'partially_refunded',
                $totals['captured_minor'] >= (int) $booking->total_minor && $totals['captured_minor'] > 0 => 'paid',
                $totals['captured_minor'] > 0 => 'partial',
                default => 'unpaid',
            };
            DB::table('bookings')->where('id', $bookingId)->update([
                'payment_status' => $status,
                'amount_paid_minor' => $totals['net_paid_minor'],
                'amount_paid_currency' => $booking->total_currency,
                'updated_at' => now(),
            ]);
        });
    }
}

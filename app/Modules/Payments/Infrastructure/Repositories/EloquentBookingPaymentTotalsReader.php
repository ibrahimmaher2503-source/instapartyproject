<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Repositories;

use App\Modules\Payments\Domain\Contracts\BookingPaymentTotalsReader;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use Brick\Money\Money;

final class EloquentBookingPaymentTotalsReader implements BookingPaymentTotalsReader
{
    public function forBooking(int $bookingId, string $currency): array
    {
        $payments = Payment::query()->where('booking_id', $bookingId)
            ->where('amount_currency', $currency)
            ->whereIn('status', ['captured', 'partially_refunded', 'refunded']);
        $captured = Money::ofMinor((int) (clone $payments)->sum('amount_minor'), $currency);
        $refunded = Money::ofMinor((int) Refund::query()
            ->whereIn('payment_id', $payments->select('id'))
            ->where('amount_currency', $currency)
            ->where('status', 'completed')->sum('amount_minor'), $currency);
        $hasPendingRefund = Refund::query()
            ->whereIn('payment_id', $payments->select('id'))
            ->whereIn('status', ['pending', 'processing'])->exists();

        return [
            'captured_minor' => $captured->getMinorAmount()->toInt(),
            'refunded_minor' => $refunded->getMinorAmount()->toInt(),
            'has_pending_refund' => $hasPendingRefund,
            'net_paid_minor' => Money::max(Money::ofMinor(0, $currency), $captured->minus($refunded))
                ->getMinorAmount()->toInt(),
        ];
    }
}

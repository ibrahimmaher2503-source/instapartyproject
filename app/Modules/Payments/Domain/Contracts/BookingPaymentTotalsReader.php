<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

interface BookingPaymentTotalsReader
{
    /** @return array{captured_minor: int, refunded_minor: int, has_pending_refund: bool, net_paid_minor: int} */
    public function forBooking(int $bookingId, string $currency): array;
}

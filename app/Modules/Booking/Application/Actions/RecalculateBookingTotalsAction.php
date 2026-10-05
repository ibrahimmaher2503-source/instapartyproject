<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Support\Facades\DB;

final class RecalculateBookingTotalsAction
{
    public function execute(Booking $booking): Booking
    {
        $vendorDelivery = (int) DB::table('booking_vendors')
            ->where('booking_id', $booking->id)
            ->sum('delivery_fee_minor');
        $adjustments = (int) DB::table('booking_adjustments')
            ->where('booking_id', $booking->id)
            ->where('type', 'transportation')
            ->sum('amount_minor');
        $subtotal = (int) DB::table('booking_vendors')
            ->where('booking_id', $booking->id)
            ->sum('subtotal_minor');
        $delivery = $vendorDelivery + (int) ($booking->transport_base_minor ?? 0) + $adjustments;

        $booking->forceFill([
            'subtotal_minor' => $subtotal,
            'delivery_total_minor' => $delivery,
            'delivery_total_currency' => $booking->total_currency ?: 'EGP',
            'total_minor' => max(0, $subtotal + $delivery - (int) $booking->discount_total_minor),
        ])->save();

        return $booking->refresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Services;

use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Support\Facades\DB;

final class BookingTransportPricingService
{
    public function snapshotBaseFee(Booking $booking): void
    {
        if ($booking->transport_base_minor !== null) {
            return;
        }

        $fee = DB::table('booking_addresses as address')
            ->join('cities as city', 'city.id', '=', 'address.city_id')
            ->join('governorates as governorate', 'governorate.id', '=', 'city.governorate_id')
            ->where('address.booking_id', $booking->id)
            ->value('governorate.transport_fee_minor');

        $currency = DB::table('booking_addresses as address')
            ->join('cities as city', 'city.id', '=', 'address.city_id')
            ->join('governorates as governorate', 'governorate.id', '=', 'city.governorate_id')
            ->where('address.booking_id', $booking->id)
            ->value('governorate.transport_fee_currency');

        if ($fee === null) {
            return;
        }

        $booking->forceFill([
            'transport_base_minor' => (int) $fee,
            'transport_base_currency' => (string) ($currency ?: $booking->total_currency ?: 'EGP'),
            'transport_base_source' => 'governorate:'.$this->governorateCode($booking),
        ])->save();
    }

    private function governorateCode(Booking $booking): string
    {
        return (string) (DB::table('booking_addresses as address')
            ->join('cities as city', 'city.id', '=', 'address.city_id')
            ->join('governorates as governorate', 'governorate.id', '=', 'city.governorate_id')
            ->where('address.booking_id', $booking->id)
            ->value('governorate.code') ?? 'unknown');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\Booking;
use Brick\Money\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RecalculateBookingTotalsAction
{
    public function execute(Booking $booking): Booking
    {
        $currency = (string) ($booking->total_currency ?: 'EGP');

        foreach ([
            ['subtotal_minor', 'subtotal_currency'],
            ['delivery_total_minor', 'delivery_total_currency'],
            ['discount_total_minor', 'discount_total_currency'],
            ['loyalty_redeemed_minor', 'loyalty_redeemed_currency'],
            ['transport_base_minor', 'transport_base_currency'],
        ] as [$amountField, $currencyField]) {
            if ((int) $booking->{$amountField} > 0 && (string) $booking->{$currencyField} !== $currency) {
                throw new DomainException('booking.currency_mismatch');
            }
        }

        $vendorCurrencyMismatch = DB::table('booking_vendors')
            ->where('booking_id', $booking->id)
            ->where(function ($query) use ($currency): void {
                $query->where(function ($query) use ($currency): void {
                    $query->where('subtotal_minor', '>', 0)->where('subtotal_currency', '<>', $currency);
                })->orWhere(function ($query) use ($currency): void {
                    $query->where('delivery_fee_minor', '>', 0)->where('delivery_fee_currency', '<>', $currency);
                });
            })
            ->exists();

        $adjustmentCurrencyMismatch = DB::table('booking_adjustments')
            ->where('booking_id', $booking->id)
            ->where('type', 'transportation')
            ->where('amount_minor', '>', 0)
            ->where('currency', '<>', $currency)
            ->exists();

        if ($vendorCurrencyMismatch || $adjustmentCurrencyMismatch) {
            throw new DomainException('booking.currency_mismatch');
        }

        $vendorDeliveryMinor = (int) DB::table('booking_vendors')
            ->where('booking_id', $booking->id)
            ->sum('delivery_fee_minor');
        $adjustmentMinor = (int) DB::table('booking_adjustments')
            ->where('booking_id', $booking->id)
            ->where('type', 'transportation')
            ->sum('amount_minor');
        $subtotalMinor = (int) DB::table('booking_vendors')
            ->where('booking_id', $booking->id)
            ->sum('subtotal_minor');

        if ($subtotalMinor > 0 && (string) $booking->subtotal_currency !== $currency) {
            throw new DomainException('booking.currency_mismatch');
        }

        $delivery = Money::ofMinor($vendorDeliveryMinor, $currency)
            ->plus(Money::ofMinor((int) ($booking->transport_base_minor ?? 0), $currency))
            ->plus(Money::ofMinor($adjustmentMinor, $currency));
        $total = Money::ofMinor($subtotalMinor, $currency)
            ->plus($delivery)
            ->minus(Money::ofMinor((int) $booking->discount_total_minor, $currency))
            ->minus(Money::ofMinor((int) $booking->loyalty_redeemed_minor, $currency));

        $booking->forceFill([
            'subtotal_minor' => $subtotalMinor,
            'delivery_total_minor' => $delivery->getMinorAmount()->toInt(),
            'delivery_total_currency' => $currency,
            'total_minor' => $total->isNegative()
                ? 0
                : $total->getMinorAmount()->toInt(),
        ])->save();

        return $booking->refresh();
    }
}

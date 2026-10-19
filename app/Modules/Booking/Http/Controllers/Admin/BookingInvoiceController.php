<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Admin;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Payments\Domain\Contracts\BookingPaymentTotalsReader;
use Brick\Money\Money;
use Illuminate\Contracts\View\View;

final class BookingInvoiceController
{
    public function __invoke(string $bookingPublicId): View
    {
        $booking = Booking::query()
            ->where('public_id', $bookingPublicId)
            ->with(['customer', 'address.city.governorate', 'vendors.vendor', 'vendors.items', 'payments', 'adjustments'])
            ->firstOrFail();

        $format = fn (int $minor, string $currency): string => Money::ofMinor($minor, $currency)->formatTo(app()->getLocale());
        $adjustments = $booking->adjustments->where('type', 'transportation');
        $currency = (string) $booking->subtotal_currency;
        $itemSubtotal = Money::ofMinor(0, $currency);
        foreach ($booking->vendors as $vendor) {
            foreach ($vendor->items as $item) {
                if ($item->line_total_currency !== $currency) {
                    abort(409, __('booking::booking.errors.currency_mismatch.message'));
                }
                $itemSubtotal = $itemSubtotal->plus(Money::ofMinor((int) $item->line_total_minor, $currency));
            }
        }

        return view('booking.invoice', [
            'booking' => $booking,
            'format' => $format,
            'vendorDeliveryMinor' => $booking->vendors->sum('delivery_fee_minor'),
            'transportAdjustmentMinor' => $adjustments->sum('amount_minor'),
            'adjustments' => $adjustments,
            'itemSubtotalMinor' => $itemSubtotal->getMinorAmount()->toInt(),
            'subtotalReconciliationMinor' => Money::ofMinor((int) $booking->subtotal_minor, $currency)
                ->minus($itemSubtotal)->getMinorAmount()->toInt(),
            'paymentTotals' => app(BookingPaymentTotalsReader::class)->forBooking($booking->id, (string) $booking->total_currency),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Admin;

use App\Modules\Booking\Domain\Models\Booking;
use Brick\Money\Money;
use Illuminate\Contracts\View\View;

final class BookingInvoiceController
{
    public function __invoke(string $bookingPublicId): View
    {
        $booking = Booking::query()
            ->where('public_id', $bookingPublicId)
            ->with(['customer', 'address.city.governorate', 'vendors.items', 'adjustments'])
            ->firstOrFail();

        $format = fn (int $minor, string $currency): string => Money::ofMinor($minor, $currency)->formatTo(app()->getLocale());
        $adjustments = $booking->adjustments->where('type', 'transportation');

        return view('booking.invoice', [
            'booking' => $booking,
            'format' => $format,
            'vendorDeliveryMinor' => $booking->vendors->sum('delivery_fee_minor'),
            'transportAdjustmentMinor' => $adjustments->sum('amount_minor'),
            'adjustments' => $adjustments,
        ]);
    }
}

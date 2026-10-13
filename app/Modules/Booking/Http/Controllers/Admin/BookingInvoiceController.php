<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Admin;

use App\Modules\Booking\Application\Actions\BuildBookingInvoiceDataAction;
use Illuminate\Contracts\View\View;

final class BookingInvoiceController
{
    public function __invoke(string $bookingPublicId, BuildBookingInvoiceDataAction $action): View
    {
        return view('booking.invoice', $action->execute($bookingPublicId));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Web;

use App\Modules\Booking\Application\Actions\BuildBookingInvoiceDataAction;
use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Contracts\View\View;

final class CustomerBookingInvoiceController
{
    public function __invoke(string $bookingPublicId, BuildBookingInvoiceDataAction $action): View
    {
        Booking::query()
            ->where('public_id', $bookingPublicId)
            ->where('customer_id', auth()->id())
            ->firstOrFail();

        return view('booking.invoice', $action->execute($bookingPublicId) + ['customerDocument' => true]);
    }
}

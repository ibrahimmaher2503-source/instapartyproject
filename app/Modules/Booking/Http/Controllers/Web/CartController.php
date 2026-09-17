<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Web;

use App\Modules\Booking\Application\Actions\AddServiceToEventAction;
use App\Modules\Booking\Application\Actions\RemoveItemFromBookingAction;
use App\Modules\Booking\Application\Actions\SubmitBookingAction;
use App\Modules\Booking\Application\DTOs\SubmitBookingDTO;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Http\Requests\StorefrontBookingRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CartController
{
    public function __invoke(): View
    {
        $booking = auth()->check()
            ? Booking::query()
                ->where('customer_id', auth()->id())
                ->whereState('lifecycle_status', DraftState::class)
                ->with(['vendors.items', 'vendors.vendor', 'address'])
                ->latest('id')
                ->first()
            : null;

        return view('storefront.cart', ['booking' => $booking]);
    }

    public function setup(StorefrontBookingRequest $request, AddServiceToEventAction $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $action->execute((int) $user->id, $request->toDraftDTO(), $request->servicePublicId(), $request->quantity());

        return redirect()->route('storefront.cart')->with('booking_added', true);
    }

    public function remove(
        string $bookingPublicId,
        string $itemPublicId,
        BookingRepository $bookings,
        RemoveItemFromBookingAction $action,
    ): RedirectResponse {
        $booking = $bookings->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== auth()->id(), 404);
        $action->execute($booking->id, (int) auth()->id(), $itemPublicId);

        return redirect()->route('storefront.cart');
    }

    public function submit(
        string $bookingPublicId,
        BookingRepository $bookings,
        SubmitBookingAction $action,
    ): RedirectResponse {
        $booking = $bookings->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== auth()->id(), 404);

        $action->execute(new SubmitBookingDTO(
            bookingId: $booking->id,
            customerId: (int) auth()->id(),
            idempotencyKey: (string) Str::uuid(),
            requestContent: 'storefront-submit:'.$booking->public_id,
        ));

        return redirect()->route('storefront.cart')->with('booking_submitted', true);
    }
}

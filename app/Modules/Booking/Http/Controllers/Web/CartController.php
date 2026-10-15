<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Web;

use App\Modules\Booking\Application\Actions\AddServiceToEventAction;
use App\Modules\Booking\Application\Actions\RemoveItemFromBookingAction;
use App\Modules\Booking\Application\Actions\SubmitBookingAction;
use App\Modules\Booking\Application\Actions\UpdateCartItemQuantityAction;
use App\Modules\Booking\Application\DTOs\SubmitBookingDTO;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Exceptions\BelowCoverageMinimumException;
use App\Modules\Booking\Domain\Exceptions\CurrencyMismatchException;
use App\Modules\Booking\Domain\Exceptions\DeliveryCityRequiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Http\Requests\StorefrontBookingRequest;
use App\Modules\Shared\Application\Actions\GetPublishedCmsPageAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CartController
{
    public function __invoke(GetPublishedCmsPageAction $cmsPages): View
    {
        $booking = auth()->check()
            ? Booking::query()
                ->where('customer_id', auth()->id())
                ->whereState('lifecycle_status', DraftState::class)
                ->with(['vendors.items', 'vendors.vendor', 'address'])
                ->latest('id')
                ->first()
            : null;

        return view('storefront.cart', [
            'booking' => $booking,
            'refundPolicy' => $cmsPages->execute('refund-policy'),
        ]);
    }

    public function setup(StorefrontBookingRequest $request, AddServiceToEventAction $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $action->execute((int) $user->id, $request->toDraftDTO(), $request->servicePublicId(), $request->quantity(), $request->packagePublicId());
        $request->session()->forget('storefront.planner_notes');

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

    public function updateQuantity(
        Request $request,
        string $bookingPublicId,
        string $itemPublicId,
        BookingRepository $bookings,
        UpdateCartItemQuantityAction $action,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $booking = $bookings->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== $user->id, 404);
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        $action->execute($booking->id, (int) $user->id, $itemPublicId, (int) $validated['quantity']);

        return redirect()->route('storefront.cart');
    }

    public function submit(
        string $bookingPublicId,
        BookingRepository $bookings,
        SubmitBookingAction $action,
    ): RedirectResponse {
        $booking = $bookings->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== auth()->id(), 404);

        try {
            $submittedBooking = $action->execute(new SubmitBookingDTO(
                bookingId: $booking->id,
                customerId: (int) auth()->id(),
                idempotencyKey: (string) Str::uuid(),
                requestContent: 'storefront-submit:'.$booking->public_id,
            ));
        } catch (BelowCoverageMinimumException) {
            return redirect()->route('storefront.cart')->withErrors(['booking' => __('booking.errors.below_coverage_minimum.message')]);
        } catch (DeliveryCityRequiredException) {
            return redirect()->route('storefront.cart')->withErrors(['booking' => __('booking.errors.delivery_city_required.message')]);
        } catch (CurrencyMismatchException) {
            return redirect()->route('storefront.cart')->withErrors(['booking' => __('account.booking_submit_failed')]);
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }

            $message = $exception->getStatusCode() === 422
                && $exception->getMessage() === __('storefront.wizard.validation.service_occasion_mismatch')
                ? $exception->getMessage()
                : __($exception->getStatusCode() === 409 ? 'account.booking_submit_conflict' : 'account.booking_submit_empty');

            return redirect()->route('storefront.cart')->withErrors(['booking' => $message]);
        }

        return redirect()->route('storefront.account.bookings.show', $submittedBooking->public_id)
            ->with('booking_submitted', $submittedBooking->public_id);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Enums\CustomerConsentStatus;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Identity\Application\Actions\UpdateCustomerProfileAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Requests\UpdateCustomerProfileRequest;
use App\Modules\Payments\Application\DTOs\RefundTimelineEntryDto;
use App\Modules\Payments\Domain\Contracts\BookingPaymentTotalsReader;
use App\Modules\Payments\Domain\Contracts\RefundTimelineReader;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reviews\Domain\Contracts\BookingItemReviewabilityReader;
use App\Modules\Reviews\Domain\Contracts\CustomerReviewReader;
use App\Modules\Reviews\Domain\Contracts\ServiceReviewRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class CustomerAccountController
{
    public function dashboard(Request $request): View
    {
        $user = $this->user($request);
        $bookings = Booking::query()->where('customer_id', $user->id)->with(['vendors.vendor', 'vendors.items'])->latest('created_at')->get();
        $upcoming = $bookings->first(fn (Booking $booking): bool => $booking->event_starts_at?->isFuture() ?? false);

        return view('storefront.account.dashboard', compact('user', 'upcoming') + [
            'activeBookings' => $bookings->filter(fn (Booking $booking): bool => ! in_array($booking->lifecycle_status->getValue(), ['cancelled', 'completed'], true))->count(),
            'upcomingCount' => $bookings->filter(fn (Booking $booking): bool => $booking->event_starts_at?->isFuture() ?? false)->count(),
            'unreadCount' => $this->notificationsQuery($user)->whereNull('read_at')->count(),
        ]);
    }

    public function bookings(Request $request): View
    {
        $bookings = Booking::query()->where('customer_id', $this->user($request)->id)->with(['vendors.vendor', 'vendors.items'])->latest('created_at')->paginate(10);

        return view('storefront.account.bookings', compact('bookings'));
    }

    public function booking(
        Request $request,
        string $publicId,
        RefundTimelineReader $refundReader,
        BookingPaymentTotalsReader $paymentTotalsReader,
        CatalogServiceReader $catalogReader,
        BookingItemReviewabilityReader $reviewability,
        ServiceReviewRepository $reviews,
    ): View {
        $booking = Booking::query()->where('customer_id', $this->user($request)->id)->where('public_id', $publicId)->with(['vendors.vendor', 'vendors.items.service', 'occasion', 'address.city', 'payments', 'customerNotes'])->firstOrFail();
        $refunds = $refundReader->findByBookingId($booking->id);
        $cancellation = null;

        if ($booking->lifecycle_status->getValue() === LifecycleStatus::Cancelled->value) {
            $paymentTotals = $paymentTotalsReader->forBooking($booking->id, (string) $booking->total_currency);
            $transition = $booking->stateTransitions()
                ->where('to_state', LifecycleStatus::Cancelled->value)
                ->latest('id')
                ->first(['trigger_kind', 'context']);
            $interventionId = $transition?->trigger_kind === 'admin'
                ? ($transition->context['intervention_id'] ?? null)
                : null;
            $reason = $transition?->trigger_kind === 'customer'
                ? ($transition->context['reason'] ?? null)
                : null;
            $latestRefund = $refunds->first();

            if ($transition?->trigger_kind === 'admin' && $interventionId !== null) {
                $reason = $booking->adminInterventions()
                    ->whereKey($interventionId)
                    ->where('intervention_type', InterventionType::ForceCancel->value)
                    ->value('reason');
            }
            $refundGuidance = match (true) {
                $refunds->contains(static fn (RefundTimelineEntryDto $refund): bool => in_array(
                    $refund->status,
                    [RefundStatus::Pending, RefundStatus::Processing],
                    true,
                )) => 'in_progress',
                $latestRefund?->status === RefundStatus::Failed => 'failed',
                $paymentTotals['refunded_minor'] > 0 && $paymentTotals['net_paid_minor'] > 0 => 'partial_completed',
                $paymentTotals['refunded_minor'] > 0 => 'full_completed',
                $paymentTotals['captured_minor'] > 0 => 'not_requested',
                default => 'no_payment',
            };

            $cancellation = [
                'reason' => is_string($reason) && filled($reason) ? trim($reason) : null,
                'payment_totals' => $paymentTotals,
                'refund_guidance' => $refundGuidance,
            ];
        }

        $availableServices = [];
        $reviewableItems = [];

        foreach ($booking->vendors as $vendor) {
            foreach ($vendor->items as $item) {
                $availableServices[$item->public_id] = $item->service_id === null ? null : $catalogReader->findPublishedById((int) $item->service_id);
                $reviewableItems[$item->public_id] = $reviewability->isReviewable($item->public_id, $booking->customer_id)
                    && $reviews->findByBookingItemId($item->id) === null;
            }
        }

        $pendingVendorProposals = BookingAdminIntervention::query()
            ->where('booking_id', $booking->getKey())
            ->where('intervention_type', InterventionType::VendorProposal->value)
            ->where('customer_consent_status', CustomerConsentStatus::Pending->value)
            ->where(function ($query): void {
                $query->whereNull('consent_expires_at')->orWhere('consent_expires_at', '>', now('UTC'));
            })
            ->latest('id')
            ->get();

        return view('storefront.account.booking', compact('booking', 'refunds', 'availableServices', 'reviewableItems', 'cancellation', 'pendingVendorProposals'));
    }

    public function payments(Request $request, RefundTimelineReader $refundReader): View
    {
        $user = $this->user($request);
        $payments = Payment::query()->where('user_id', $user->id)->with('booking')->latest('created_at')->paginate(15);
        $customerBookings = $payments->getCollection()
            ->map(fn (Payment $payment) => $payment->booking)
            ->filter(fn ($booking): bool => $booking instanceof Booking && $booking->customer_id === $user->id)
            ->unique('id')
            ->keyBy('id');
        $refundsByBooking = $refundReader->findByBookingIds(
            $customerBookings->keys()->map(static fn ($bookingId): int => (int) $bookingId)->all(),
        );
        $refunds = collect($refundsByBooking)
            ->flatMap(function ($bookingRefunds, $bookingId) use ($customerBookings): array {
                $booking = $customerBookings->get($bookingId);

                if ($booking === null) {
                    return [];
                }

                return $bookingRefunds->map(fn ($refund): array => [
                    'booking_public_id' => $booking->public_id,
                    'booking_reference' => $booking->reference_no,
                    'refund' => $refund,
                ])->all();
            })
            ->values();

        return view('storefront.account.payments', compact('payments', 'refunds'));
    }

    public function plans(): View
    {
        return view('storefront.account.plans');
    }

    public function favorites(Request $request): View
    {
        $wishlist = Wishlist::query()->where('user_id', $this->user($request)->id)->with('items.service.vendor')->first();

        return view('storefront.account.favorites', ['items' => $wishlist?->items ?? collect()]);
    }

    public function notifications(Request $request): View
    {
        $notifications = $this->notificationsQuery($this->user($request))->latest('id')->paginate(20);

        return view('storefront.account.notifications', compact('notifications'));
    }

    public function reviews(Request $request, CustomerReviewReader $reviewReader): View
    {
        $reviews = $reviewReader->listForCustomer($this->user($request)->id);

        return view('storefront.account.reviews', compact('reviews'));
    }

    public function profile(Request $request): View
    {
        return view('storefront.account.profile', ['user' => $this->user($request)->load('customerProfile')]);
    }

    public function updateProfile(UpdateCustomerProfileRequest $request, UpdateCustomerProfileAction $action): RedirectResponse
    {
        $action->execute($this->user($request), $request->toDTO());

        return back()->with('status', __('account.profile_saved'));
    }

    public function security(): View
    {
        return view('storefront.account.security');
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string'], 'new_password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()]]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => __('account.password_invalid')]);
        }
        $this->user($request)->update(['password' => Hash::make($data['new_password'])]);

        return back()->with('status', __('account.password_saved'));
    }

    private function notificationsQuery(User $user)
    {
        return NotificationDispatch::query()->where('user_id', $user->id)->where('channel', NotificationChannel::InApp);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        return $request->user();
    }
}

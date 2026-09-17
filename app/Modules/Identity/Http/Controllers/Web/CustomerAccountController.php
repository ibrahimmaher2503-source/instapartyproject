<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Discovery\Domain\Models\Wishlist;
use App\Modules\Identity\Application\Actions\UpdateCustomerProfileAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Requests\UpdateCustomerProfileRequest;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
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

    public function booking(Request $request, string $publicId): View
    {
        $booking = Booking::query()->where('customer_id', $this->user($request)->id)->where('public_id', $publicId)->with(['vendors.vendor', 'vendors.items.service', 'occasion', 'address', 'payments'])->firstOrFail();

        return view('storefront.account.booking', compact('booking'));
    }

    public function payments(Request $request): View
    {
        $payments = Payment::query()->where('user_id', $this->user($request)->id)->with('booking')->latest('created_at')->paginate(15);

        return view('storefront.account.payments', compact('payments'));
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

    public function reviews(Request $request): View
    {
        $id = $this->user($request)->id;
        $reviews = ServiceReview::query()->where('user_id', $id)->latest('created_at')->get()->map(fn ($r): array => ['type' => 'service', 'review' => $r])
            ->merge(VendorReview::query()->where('user_id', $id)->latest('created_at')->get()->map(fn ($r): array => ['type' => 'vendor', 'review' => $r]))->sortByDesc(fn (array $i) => $i['review']->created_at);

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

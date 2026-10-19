<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers\Web;

use App\Modules\Booking\Domain\Contracts\BookingRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class CustomerAccountNotificationController
{
    public function index(Request $request, BookingRepository $bookings): View
    {
        $user = $request->user() ?? abort(401);
        $notifications = $user->notifications()->latest('created_at')->paginate(20);
        $bookingUrls = [];

        foreach ($notifications as $notification) {
            $url = $this->ownedBookingUrl($notification, $bookings, (int) $user->getAuthIdentifier());
            if ($url !== null) {
                $bookingUrls[$notification->id] = $url;
            }
        }

        return view('storefront.account.notifications', compact('notifications', 'bookingUrls'));
    }

    public function markRead(Request $request, string $notificationId): RedirectResponse
    {
        $this->notification($request, $notificationId)->markAsRead();

        return redirect()->route('storefront.account.notifications', ['locale' => app()->getLocale()]);
    }

    public function markUnread(Request $request, string $notificationId): RedirectResponse
    {
        $this->notification($request, $notificationId)->markAsUnread();

        return redirect()->route('storefront.account.notifications', ['locale' => app()->getLocale()]);
    }

    private function notification(Request $request, string $notificationId): DatabaseNotification
    {
        $user = $request->user() ?? abort(401);

        return $user->notifications()->whereKey($notificationId)->firstOrFail();
    }

    private function ownedBookingUrl(DatabaseNotification $notification, BookingRepository $bookings, int $customerId): ?string
    {
        $actionUrl = $notification->data['action_url'] ?? null;
        $path = is_string($actionUrl) ? parse_url($actionUrl, PHP_URL_PATH) : null;

        if (! is_string($path) || preg_match('~^/(?:en|ar)/account/bookings/([0-7][0-9A-HJKMNP-TV-Z]{25})$~D', $path, $matches) !== 1) {
            return null;
        }

        $booking = $bookings->findByPublicId($matches[1]);
        if ($booking === null || (int) $booking->customer_id !== $customerId) {
            return null;
        }

        return route('storefront.account.bookings.show', [
            'locale' => app()->getLocale(),
            'publicId' => $matches[1],
        ]);
    }
}

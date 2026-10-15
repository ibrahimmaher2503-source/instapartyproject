<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Booking\Domain\Contracts\CustomerBookingChatReader;
use App\Modules\Booking\Domain\DTOs\CustomerBookingChatReference;
use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Support\Collection;

final class EloquentCustomerBookingChatReader implements CustomerBookingChatReader
{
    public function findOwnedBookings(int $customerId, array $bookingIds): Collection
    {
        if ($bookingIds === []) {
            return collect();
        }

        return Booking::query()
            ->where('customer_id', $customerId)
            ->whereIn('id', array_values(array_unique(array_map('intval', $bookingIds))))
            ->get(['id', 'public_id', 'reference_no', 'event_starts_at'])
            ->mapWithKeys(fn (Booking $booking): array => [
                $booking->id => new CustomerBookingChatReference(
                    bookingId: (int) $booking->id,
                    publicId: (string) $booking->public_id,
                    referenceNumber: (string) $booking->reference_no,
                    eventStartsAt: $booking->event_starts_at,
                ),
            ]);
    }
}

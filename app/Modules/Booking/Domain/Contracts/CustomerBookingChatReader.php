<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Contracts;

use App\Modules\Booking\Domain\DTOs\CustomerBookingChatReference;
use Illuminate\Support\Collection;

interface CustomerBookingChatReader
{
    /** @param list<int> $bookingIds
     * @return Collection<int, CustomerBookingChatReference> keyed by internal booking id
     */
    public function findOwnedBookings(int $customerId, array $bookingIds): Collection;
}

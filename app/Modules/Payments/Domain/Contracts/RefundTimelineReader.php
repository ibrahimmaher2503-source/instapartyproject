<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Payments\Application\DTOs\RefundTimelineEntryDto;
use Illuminate\Support\Collection;

interface RefundTimelineReader
{
    /**
     * Return all refund rows attached to the given booking, ordered by
     * created_at DESC (most recent first). Returns an empty collection
     * when there are none.
     *
     * @return Collection<int, RefundTimelineEntryDto>
     */
    public function findByBookingId(int $bookingId): Collection;

    /**
     * Return refund rows for the given bookings, grouped by booking ID.
     *
     * @param  array<int, int>  $bookingIds
     * @return array<int, Collection<int, RefundTimelineEntryDto>>
     */
    public function findByBookingIds(array $bookingIds): array;
}

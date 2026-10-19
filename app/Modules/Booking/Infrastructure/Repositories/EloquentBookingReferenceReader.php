<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Booking\Domain\Contracts\BookingReferenceReader;
use App\Modules\Booking\Domain\Models\Booking;

final class EloquentBookingReferenceReader implements BookingReferenceReader
{
    /** @var array<int, string|null> */
    private array $referencesById = [];

    public function referenceNoFor(int $bookingId): ?string
    {
        if (! array_key_exists($bookingId, $this->referencesById)) {
            $this->referencesById[$bookingId] = Booking::query()
                ->whereKey($bookingId)
                ->value('reference_no');
        }

        return $this->referencesById[$bookingId];
    }
}

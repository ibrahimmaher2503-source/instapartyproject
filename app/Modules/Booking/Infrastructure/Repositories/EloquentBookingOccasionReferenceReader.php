<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Repositories;

use App\Modules\Booking\Domain\Contracts\BookingOccasionReferenceReader;
use App\Modules\Booking\Domain\Models\Booking;

class EloquentBookingOccasionReferenceReader implements BookingOccasionReferenceReader
{
    public function hasReferencesToOccasion(int $occasionId): bool
    {
        return Booking::withTrashed()->where('occasion_id', $occasionId)->exists();
    }
}

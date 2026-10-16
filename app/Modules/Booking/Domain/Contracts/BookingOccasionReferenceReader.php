<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Contracts;

interface BookingOccasionReferenceReader
{
    public function hasReferencesToOccasion(int $occasionId): bool;
}

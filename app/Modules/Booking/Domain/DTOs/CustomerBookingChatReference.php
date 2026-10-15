<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\DTOs;

use Illuminate\Support\Carbon;

final readonly class CustomerBookingChatReference
{
    public function __construct(
        public int $bookingId,
        public string $publicId,
        public string $referenceNumber,
        public ?Carbon $eventStartsAt,
    ) {}
}

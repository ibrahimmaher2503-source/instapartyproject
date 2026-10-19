<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

use Carbon\Carbon;

final readonly class CreateBookingDraftDTO
{
    /** A practical input bound, not a product-defined event-size cap. */
    public const MAX_GUEST_COUNT = 10_000;

    public function __construct(
        public int $customerId,
        public int $occasionId,
        public Carbon $eventStartsAt,
        public ?Carbon $eventEndsAt,
        public ?int $guestCount,
        /** @var array<string, mixed>|null */
        public ?array $theme,
        public ?string $celebrantName,
        public ?string $celebrantDob,
        public ?string $celebrantGender,
        public int $addressCityId,
        public string $addressLine,
        public ?string $addressBuilding,
        public ?string $addressFloor,
        public ?string $addressApartment,
        public ?string $addressLandmark,
        public ?float $addressLatitude,
        public ?float $addressLongitude,
        public string $recipientName,
        public string $recipientPhoneE164,
        public ?string $notes = null,
        public string $notesLocale = 'en',
    ) {}
}

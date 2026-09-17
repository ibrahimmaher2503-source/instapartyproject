<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Identity\Domain\Models\User;

final readonly class BookingItemFulfilled
{
    /**
     * @param  array{from: string, to: string}  $stateHop
     */
    public function __construct(
        public BookingItem $item,
        public User $actor,
        public FulfillmentLane $lane,
        public array $stateHop,
    ) {}
}

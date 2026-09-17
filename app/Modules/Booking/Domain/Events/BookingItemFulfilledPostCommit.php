<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Domain\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

final readonly class BookingItemFulfilledPostCommit implements AuditableEvent
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

    public function auditable(): Model
    {
        return $this->item;
    }

    public function actor(): ?User
    {
        return $this->actor;
    }

    public function action(): string
    {
        return 'booking.item.fulfilled.'.$this->lane->value;
    }

    public function changes(): array
    {
        return [
            'lane' => $this->lane->value,
            'product_type' => $this->item->product_type->value,
            'from_state' => $this->stateHop['from'],
            'to_state' => $this->stateHop['to'],
        ];
    }
}

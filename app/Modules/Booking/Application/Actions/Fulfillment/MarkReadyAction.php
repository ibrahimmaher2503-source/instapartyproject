<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment;

use App\Modules\Booking\Application\Actions\Fulfillment\Internal\MarkRentalReadyAction;
use App\Modules\Booking\Application\Actions\Fulfillment\Internal\MarkSaleReadyAction;
use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Exceptions\LaneNotSupportedForTypeException;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;

class MarkReadyAction
{
    public function __construct(
        private readonly MarkRentalReadyAction $rental,
        private readonly MarkSaleReadyAction $sale,
    ) {}

    public function execute(BookingItem $item, VendorProfile $vendor, User $actor): BookingItem
    {
        return match ($item->product_type) {
            ProductType::Rental => $this->rental->execute($item, $vendor, $actor),
            ProductType::Sale => $this->sale->execute($item, $vendor, $actor),
            ProductType::Digital => throw new LaneNotSupportedForTypeException(FulfillmentLane::Ready, ProductType::Digital),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment;

use App\Modules\Booking\Application\Actions\Fulfillment\Internal\MarkRentalPreparingAction;
use App\Modules\Booking\Application\Actions\Fulfillment\Internal\MarkSalePreparingAction;
use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Exceptions\LaneNotSupportedForTypeException;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;

class MarkPreparingAction
{
    public function __construct(
        private readonly MarkRentalPreparingAction $rental,
        private readonly MarkSalePreparingAction $sale,
    ) {}

    public function execute(BookingItem $item, VendorProfile $vendor, User $actor): BookingItem
    {
        return match ($item->product_type) {
            ProductType::Rental => $this->rental->execute($item, $vendor, $actor),
            ProductType::Sale => $this->sale->execute($item, $vendor, $actor),
            ProductType::Digital => throw new LaneNotSupportedForTypeException(FulfillmentLane::Preparing, ProductType::Digital),
        };
    }
}

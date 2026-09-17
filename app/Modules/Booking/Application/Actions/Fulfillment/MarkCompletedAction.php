<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment;

use App\Modules\Booking\Application\Actions\Fulfillment\Internal\MarkDigitalCompletedAction;
use App\Modules\Booking\Application\Actions\Fulfillment\Internal\MarkRentalCompletedAction;
use App\Modules\Booking\Application\Actions\Fulfillment\Internal\MarkSaleCompletedAction;
use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;

class MarkCompletedAction
{
    public function __construct(
        private readonly MarkRentalCompletedAction $rental,
        private readonly MarkSaleCompletedAction $sale,
        private readonly MarkDigitalCompletedAction $digital,
    ) {}

    public function execute(
        BookingItem $item,
        VendorProfile $vendor,
        User $actor,
        FulfillmentEvidenceDto $evidence,
    ): BookingItem {
        return match ($item->product_type) {
            ProductType::Rental => $this->rental->execute($item, $vendor, $actor, $evidence),
            ProductType::Sale => $this->sale->execute($item, $vendor, $actor, $evidence),
            ProductType::Digital => $this->digital->execute($item, $vendor, $actor, $evidence),
        };
    }
}

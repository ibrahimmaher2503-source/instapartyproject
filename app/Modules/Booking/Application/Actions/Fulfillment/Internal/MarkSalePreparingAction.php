<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment\Internal;

use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\States\SaleItemStatus\InPreparationState;

class MarkSalePreparingAction extends AbstractMarkFulfillmentAction
{
    protected function lane(): FulfillmentLane
    {
        return FulfillmentLane::Preparing;
    }

    protected function targetStates(): array
    {
        return [InPreparationState::class];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment\Internal;

use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\States\RentalItemStatus\TeardownState;

final class MarkRentalTeardownAction extends AbstractMarkFulfillmentAction
{
    protected function lane(): FulfillmentLane
    {
        return FulfillmentLane::InProgress;
    }

    protected function targetStates(): array
    {
        return [TeardownState::class];
    }
}

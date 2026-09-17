<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment\Internal;

use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\States\SaleItemStatus\DeliveredState;
use App\Modules\Identity\Domain\Models\User;

class MarkSaleCompletedAction extends AbstractMarkFulfillmentAction
{
    protected function lane(): FulfillmentLane
    {
        return FulfillmentLane::Completed;
    }

    protected function targetStates(): array
    {
        return [DeliveredState::class];
    }

    protected function persistEvidence(BookingItem $item, User $actor, ?FulfillmentEvidenceDto $evidence): void
    {
        $this->writeEvidenceColumns($item, $actor, $evidence);
    }
}

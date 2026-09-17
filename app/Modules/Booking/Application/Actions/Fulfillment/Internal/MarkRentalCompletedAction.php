<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment\Internal;

use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\States\RentalItemStatus\PickedUpState;
use App\Modules\Booking\Domain\States\RentalItemStatus\TeardownState;
use App\Modules\Identity\Domain\Models\User;

/**
 * Rental Completed chains TWO transitions (Teardown → PickedUp) in one DB transaction.
 *
 * The vendor experiences one button press; audit log records both state hops via
 * StateTransition rows written by MarkBookingItemStateAction.
 */
class MarkRentalCompletedAction extends AbstractMarkFulfillmentAction
{
    protected function lane(): FulfillmentLane
    {
        return FulfillmentLane::Completed;
    }

    protected function targetStates(): array
    {
        return [TeardownState::class, PickedUpState::class];
    }

    protected function persistEvidence(BookingItem $item, User $actor, ?FulfillmentEvidenceDto $evidence): void
    {
        $this->writeEvidenceColumns($item, $actor, $evidence);
    }
}

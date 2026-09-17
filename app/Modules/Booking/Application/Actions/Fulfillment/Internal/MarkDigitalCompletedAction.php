<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment\Internal;

use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\States\DigitalItemStatus\SentState;
use App\Modules\Identity\Domain\Models\User;

/**
 * Digital vendor-side terminal: pending → sent.
 *
 * Customer redemption (sent → redeemed) is NOT triggered from this Action — it's
 * customer-driven and does not gate BookingVendor.sub_status.
 */
class MarkDigitalCompletedAction extends AbstractMarkFulfillmentAction
{
    protected function lane(): FulfillmentLane
    {
        return FulfillmentLane::Completed;
    }

    protected function targetStates(): array
    {
        return [SentState::class];
    }

    protected function persistEvidence(BookingItem $item, User $actor, ?FulfillmentEvidenceDto $evidence): void
    {
        $this->writeEvidenceColumns($item, $actor, $evidence);
    }
}

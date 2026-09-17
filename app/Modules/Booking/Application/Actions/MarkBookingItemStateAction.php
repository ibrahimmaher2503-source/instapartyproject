<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MarkBookingItemStateAction
{
    /**
     * Advance a BookingItem's per-type state machine and write the audit transition row.
     *
     * Backward-compatible: the original 3-argument signature still works. New optional
     * arguments capture vendor user actor for richer audit and an optional evidence DTO
     * that callers MAY use; this Action does NOT persist evidence itself — evidence
     * persistence remains the responsibility of the caller (so non-Completed lanes can
     * call this Action and skip evidence cleanly).
     *
     * @param  class-string  $toStateClass
     */
    public function execute(
        BookingItem $item,
        VendorProfile $vendorProfile,
        string $toStateClass,
        ?FulfillmentEvidenceDto $evidence = null,
        ?User $actor = null,
    ): BookingItem {
        return DB::transaction(function () use ($item, $vendorProfile, $toStateClass): BookingItem {
            $item->load('bookingVendor');

            abort_if(
                $item->bookingVendor->vendor_profile_id !== $vendorProfile->id,
                Response::HTTP_FORBIDDEN
            );

            $fromState = $item->item_status;
            $currentStateInstance = $item->resolveItemState();

            abort_unless(
                $currentStateInstance->canTransitionTo($toStateClass),
                Response::HTTP_CONFLICT,
                "Cannot transition from {$fromState} to {$toStateClass}"
            );

            $toStateName = $toStateClass::$name;
            $item->update(['item_status' => $toStateName]);

            StateTransition::create([
                'transitionable_type' => BookingItem::class,
                'transitionable_id' => $item->id,
                'from_state' => $fromState,
                'to_state' => $toStateName,
                'trigger_kind' => 'vendor',
                'triggered_by' => $vendorProfile->user_id,
            ]);

            return $item->refresh();
        });
    }
}

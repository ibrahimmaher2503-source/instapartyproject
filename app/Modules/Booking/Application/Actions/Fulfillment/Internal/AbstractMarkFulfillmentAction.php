<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions\Fulfillment\Internal;

use App\Modules\Booking\Application\Actions\MarkBookingItemStateAction;
use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Application\Services\FulfillmentGuards;
use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Events\BookingItemFulfilled;
use App\Modules\Booking\Domain\Events\BookingItemFulfilledPostCommit;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

/**
 * Shared transactional skeleton for inner per-type fulfillment Actions.
 *
 * Subclasses MUST define LANE and TARGET_STATES (1 or 2 state class strings).
 * The Completed lane MAY override persistEvidence() to write the four
 * evidence columns + attach the photo media.
 *
 * No public methods beyond execute() are exposed — protected helpers only,
 * preserving the .claude/rules/actions.md "one public method" rule.
 */
abstract class AbstractMarkFulfillmentAction
{
    public function __construct(
        protected readonly MarkBookingItemStateAction $mark,
        protected readonly FulfillmentGuards $guards,
    ) {}

    /**
     * @return FulfillmentLane The lane this Action handles
     */
    abstract protected function lane(): FulfillmentLane;

    /**
     * @return list<class-string> One or more state classes to transition through, in order
     */
    abstract protected function targetStates(): array;

    /**
     * Inner Actions advancing non-Completed lanes do not persist evidence.
     * MarkXxxCompletedAction subclasses override this method to call writeEvidenceColumns().
     */
    protected function persistEvidence(BookingItem $item, User $actor, ?FulfillmentEvidenceDto $evidence): void
    {
        // no-op for non-Completed lanes
    }

    /**
     * Shared evidence-persistence helper used by *CompletedAction subclasses.
     *
     * Writes the four completion columns + attaches the optional photo media row.
     * If evidence is null/empty, only completed_at and completed_by_vendor_user_id
     * are populated (with sensible defaults).
     */
    protected function writeEvidenceColumns(BookingItem $item, User $actor, ?FulfillmentEvidenceDto $evidence): void
    {
        $completedAt = $evidence?->completedAt ?? now();

        $item->update([
            'completion_note' => $evidence?->completionNote,
            'completed_at' => $completedAt,
            'completed_by_vendor_user_id' => $actor->id,
        ]);

        if ($evidence?->completionPhoto !== null) {
            $media = $item->addMedia($evidence->completionPhoto)->toMediaCollection('completion_evidence');
            $item->update(['completion_photo_media_id' => $media->id]);
        }
    }

    public function execute(
        BookingItem $item,
        VendorProfile $vendor,
        User $actor,
        ?FulfillmentEvidenceDto $evidence = null,
    ): BookingItem {
        return DB::transaction(function () use ($item, $vendor, $actor, $evidence): BookingItem {
            $bookingVendor = BookingVendor::query()
                ->whereKey($item->booking_vendor_id)
                ->lockForUpdate()
                ->firstOrFail();

            // FR-EXT-040-019 steps 1-4 (state-machine validity is step 5, enforced below by MarkBookingItemStateAction)
            $this->guards->runAll($bookingVendor, $vendor);

            $fromState = $item->item_status;

            foreach ($this->targetStates() as $targetStateClass) {
                $item = $this->mark->execute($item, $vendor, $targetStateClass, $evidence, $actor);
            }

            $finalStateName = $item->item_status;

            // Persist evidence (overridden by Completed subclasses)
            $this->persistEvidence($item, $actor, $evidence);

            // In-transaction event for sub_status aggregation
            $lane = $this->lane();
            $stateHop = ['from' => $fromState, 'to' => $finalStateName];
            event(new BookingItemFulfilled($item->refresh(), $actor, $lane, $stateHop));

            // Post-commit event for notifications + audit log
            DB::afterCommit(function () use ($item, $actor, $lane, $stateHop): void {
                event(new BookingItemFulfilledPostCommit($item->refresh(), $actor, $lane, $stateHop));
            });

            return $item->refresh();
        });
    }
}

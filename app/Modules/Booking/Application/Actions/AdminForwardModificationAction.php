<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Support\Facades\DB;

class AdminForwardModificationAction
{
    public function execute(BookingModification $modification, int $adminUserId): void
    {
        DB::transaction(function () use ($modification, $adminUserId): void {
            $modification->lockForUpdate();

            abort_if(
                $modification->status !== ModificationStatus::Draft,
                409,
                'Modification is not in draft status.',
            );

            abort_if(
                $modification->items->isEmpty(),
                422,
                'Cannot forward a modification with no changes.',
            );

            $modification->update([
                'status' => ModificationStatus::Pending,
                'expires_at' => $modification->expires_at ?? now()->addHours(48),
            ]);

            StateTransition::create([
                'transitionable_type' => BookingModification::class,
                'transitionable_id' => $modification->id,
                'from_state' => ModificationStatus::Draft->value,
                'to_state' => ModificationStatus::Pending->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $adminUserId,
                'context' => ['admin_forwarded' => true],
            ]);

            /** @var BookingVendor $bookingVendor */
            $bookingVendor = BookingVendor::query()
                ->where('id', $modification->booking_vendor_id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousSubStatus = $bookingVendor->sub_status->value;
            $bookingVendor->update(['sub_status' => VendorSubStatus::Modified]);

            StateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $bookingVendor->id,
                'from_state' => $previousSubStatus,
                'to_state' => VendorSubStatus::Modified->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $adminUserId,
            ]);

            /** @var Booking $booking */
            $booking = Booking::query()
                ->where('id', $bookingVendor->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->lifecycle_status->getValue() !== LifecycleStatus::CustomerReview->value) {
                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update(['lifecycle_status' => LifecycleStatus::CustomerReview->value]);

                StateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => $booking->lifecycle_status->getValue(),
                    'to_state' => LifecycleStatus::CustomerReview->value,
                    'trigger_kind' => 'admin',
                    'triggered_by' => $adminUserId,
                ]);
            }

            DB::afterCommit(fn () => event(new VendorModificationProposed($modification->fresh())));
        });
    }
}

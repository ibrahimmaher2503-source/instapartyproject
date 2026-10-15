<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingForceCancelled;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Shared\Domain\Models\StateTransition;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ForceCancelBookingAction
{
    public function execute(Booking $booking, AdminInterventionDTO $dto): BookingAdminIntervention
    {
        return DB::transaction(function () use ($booking, $dto): BookingAdminIntervention {
            // Take locks in the same parent-then-child order as customer cancellation.
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->lifecycle_status instanceof CompletedState) {
                throw new DomainException(__('booking::booking.cancellation.blocked.booking_completed'));
            }

            if ($booking->lifecycle_status instanceof CancelledState) {
                throw new DomainException(__('booking::booking.cancellation.blocked.already_cancelled'));
            }

            $bookingVendors = BookingVendor::query()
                ->where('booking_id', $booking->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('vendor')
                ->get();

            $engagedVendorStatuses = [
                VendorSubStatus::Pending,
                VendorSubStatus::Accepted,
                VendorSubStatus::Modified,
                VendorSubStatus::InProgress,
            ];
            $vendorsToCancel = $bookingVendors->filter(fn (BookingVendor $vendor): bool => in_array(
                $vendor->sub_status,
                [VendorSubStatus::Pending, VendorSubStatus::Modified, VendorSubStatus::Accepted],
                true,
            ));
            $engagedVendorUserIds = $bookingVendors
                ->filter(fn (BookingVendor $vendor): bool => in_array($vendor->sub_status, $engagedVendorStatuses, true))
                ->map(fn (BookingVendor $vendor) => $vendor->vendor?->user_id)
                ->filter(fn ($userId): bool => $userId !== null)
                ->map(fn ($userId): int => (int) $userId)
                ->unique()
                ->values()
                ->all();

            $beforeState = [
                'lifecycle_status' => $booking->lifecycle_status->getValue(),
                'payment_status' => $booking->payment_status->getValue(),
                'fulfillment_status' => $booking->fulfillment_status->value,
            ];

            $booking->lifecycle_status = CancelledState::class;
            $booking->cancelled_by = $dto->adminId;
            $booking->cancelled_at = now();
            $booking->save();

            $afterState = [
                'lifecycle_status' => LifecycleStatus::Cancelled->value,
                'payment_status' => $booking->payment_status->getValue(),
                'fulfillment_status' => $booking->fulfillment_status->value,
            ];

            $intervention = BookingAdminIntervention::create([
                'public_id' => Str::ulid()->toBase32(),
                'booking_id' => $booking->id,
                'admin_id' => $dto->adminId,
                'intervention_type' => InterventionType::ForceCancel,
                'reason' => $dto->reason,
                'before_state' => $beforeState,
                'after_state' => $afterState,
            ]);

            foreach ($vendorsToCancel as $vendor) {
                $fromVendorStatus = $vendor->sub_status->value;

                $vendor->update([
                    'sub_status' => VendorSubStatus::Cancelled,
                    'responded_at' => now(),
                ]);

                StateTransition::create([
                    'transitionable_type' => BookingVendor::class,
                    'transitionable_id' => $vendor->id,
                    'from_state' => $fromVendorStatus,
                    'to_state' => VendorSubStatus::Cancelled->value,
                    'trigger_kind' => 'admin',
                    'triggered_by' => $dto->adminId,
                    'context' => ['intervention_id' => $intervention->id, 'reason' => $dto->reason],
                ]);
            }

            StateTransition::create([
                'transitionable_type' => Booking::class,
                'transitionable_id' => $booking->id,
                'from_state' => $beforeState['lifecycle_status'],
                'to_state' => LifecycleStatus::Cancelled->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $dto->adminId,
                'context' => ['intervention_id' => $intervention->id, 'reason' => $dto->reason],
            ]);

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'user_id' => $dto->adminId,
                'action' => 'force_cancel_booking',
                'changes' => json_encode([
                    'before' => $beforeState,
                    'after' => $afterState,
                ]),
                'created_at' => now(),
            ]);

            $vendorProfileIds = $vendorsToCancel
                ->pluck('vendor_profile_id')
                ->unique()
                ->values()
                ->all();

            DB::afterCommit(function () use ($booking, $intervention, $engagedVendorUserIds, $vendorProfileIds): void {
                $cacheFailure = null;

                foreach ($vendorProfileIds as $vendorProfileId) {
                    $cacheKeys = ["vendor_dashboard_summary_api_{$vendorProfileId}"];
                    foreach (array_unique([app()->getLocale(), 'en', 'ar']) as $locale) {
                        $cacheKeys[] = "vendor_dashboard_stats_{$vendorProfileId}_{$locale}";
                    }
                    foreach ($cacheKeys as $cacheKey) {
                        try {
                            Cache::forget($cacheKey);
                        } catch (Throwable $exception) {
                            $cacheFailure ??= $exception;
                        }
                    }
                }

                if ($cacheFailure !== null) {
                    try {
                        Log::warning('Vendor dashboard cache invalidation failed after admin booking cancellation.', [
                            'booking_id' => $booking->id,
                            'exception' => $cacheFailure::class,
                        ]);
                    } catch (Throwable) {
                        // A logging backend failure must not block cancellation listeners either.
                    }
                }

                event(new BookingForceCancelled($booking, $intervention, $engagedVendorUserIds));
            });

            return $intervention;
        });
    }
}

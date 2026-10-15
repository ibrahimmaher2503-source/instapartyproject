<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\CancellationPreviewDTO;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

/**
 * Customer-initiated booking cancellation (audit endpoint 12.4).
 *
 * - Lifecycle guard mirrors the preview (active/completed/cancelled deny).
 * - Per-type refund eligibility via the Payments RefundPolicyResolver
 *   contract (inside PreviewBookingCancellationAction).
 * - Refund initiation happens in the Payments module via its
 *   OnBookingCancelledInitiateRefundListener (module boundary: Booking
 *   never touches Payment models/actions directly).
 * - State transition + append-only audit log + BookingCancelled afterCommit.
 */
class CancelBookingByCustomerAction
{
    public function __construct(private readonly PreviewBookingCancellationAction $preview) {}

    public function execute(Booking $booking, int $customerId, ?string $reason = null): CancellationPreviewDTO
    {
        $preview = $this->preview->execute($booking);
        $this->assertCancellable($preview);

        return DB::transaction(function () use ($booking, $customerId, $reason): CancellationPreviewDTO {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            abort_if($booking->customer_id !== $customerId, 404);

            $bookingVendors = BookingVendor::query()
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->get();
            $booking->setRelation('vendors', $bookingVendors);

            $preview = $this->preview->execute($booking);
            $this->assertCancellable($preview);

            $vendors = $bookingVendors->filter(fn (BookingVendor $vendor): bool => in_array(
                $vendor->sub_status,
                [VendorSubStatus::Pending, VendorSubStatus::Modified, VendorSubStatus::Accepted],
                true,
            ));

            $fromState = $booking->lifecycle_status->getValue();

            $booking->update([
                'lifecycle_status' => CancelledState::class,
                'cancelled_by' => $customerId,
                'cancelled_at' => now(),
            ]);

            foreach ($vendors as $vendor) {
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
                    'trigger_kind' => 'customer',
                    'triggered_by' => $customerId,
                ]);
            }

            StateTransition::create([
                'transitionable_type' => Booking::class,
                'transitionable_id' => $booking->id,
                'from_state' => $fromState,
                'to_state' => LifecycleStatus::Cancelled->value,
                'trigger_kind' => 'customer',
                'triggered_by' => $customerId,
                'context' => ['reason' => $reason, 'estimated_refund_minor' => $preview->estimatedRefundMinor],
            ]);

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'user_id' => $customerId,
                'action' => $preview->requiresManualRefundReview
                    ? 'customer_cancel_booking_requires_manual_refund_review'
                    : 'customer_cancel_booking',
                'changes' => json_encode([
                    'from' => $fromState,
                    'to' => LifecycleStatus::Cancelled->value,
                    'reason' => $reason,
                    'refundable_minor' => $preview->refundableMinor,
                    'non_refundable_minor' => $preview->nonRefundableMinor,
                    'amount_paid_minor' => $preview->amountPaidMinor,
                ]),
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($booking, $vendors): void {
                $cacheFailure = null;

                foreach ($vendors as $vendor) {
                    $cacheKeys = ["vendor_dashboard_summary_api_{$vendor->vendor_profile_id}"];
                    foreach (array_unique([app()->getLocale(), 'en', 'ar']) as $locale) {
                        $cacheKeys[] = "vendor_dashboard_stats_{$vendor->vendor_profile_id}_{$locale}";
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
                        Log::warning('Vendor dashboard cache invalidation failed after booking cancellation.', [
                            'booking_id' => $booking->id,
                            'exception' => $cacheFailure::class,
                        ]);
                    } catch (Throwable) {
                        // A logging backend failure must not block cancellation listeners either.
                    }
                }

                event(new BookingCancelled($booking));
            });

            return $preview;
        });
    }

    private function assertCancellable(CancellationPreviewDTO $preview): void
    {
        if (! $preview->cancellable) {
            throw new UnprocessableEntityHttpException(
                __('booking::booking.cancellation.blocked.'.$preview->cancellableReasonCode)
            );
        }
    }
}

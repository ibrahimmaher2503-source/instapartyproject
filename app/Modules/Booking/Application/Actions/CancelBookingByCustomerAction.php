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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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

        if (! $preview->cancellable) {
            throw new UnprocessableEntityHttpException(
                __('booking::booking.cancellation.blocked.'.$preview->cancellableReasonCode)
            );
        }

        return DB::transaction(function () use ($booking, $customerId, $reason, $preview): CancellationPreviewDTO {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            abort_if($booking->customer_id !== $customerId, 404);

            $fromState = $booking->lifecycle_status->getValue();

            $booking->update([
                'lifecycle_status' => CancelledState::class,
                'cancelled_by' => $customerId,
                'cancelled_at' => now(),
            ]);

            $vendors = BookingVendor::query()
                ->where('booking_id', $booking->id)
                ->whereIn('sub_status', [VendorSubStatus::Pending, VendorSubStatus::Modified])
                ->lockForUpdate()
                ->get();

            foreach ($vendors as $vendor) {
                $vendor->update([
                    'sub_status' => VendorSubStatus::Cancelled,
                    'responded_at' => now(),
                ]);

                StateTransition::create([
                    'transitionable_type' => BookingVendor::class,
                    'transitionable_id' => $vendor->id,
                    'from_state' => $vendor->getOriginal('sub_status'),
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

            DB::afterCommit(fn () => event(new BookingCancelled($booking)));

            return $preview;
        });
    }
}

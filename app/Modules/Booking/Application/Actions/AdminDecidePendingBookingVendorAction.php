<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\AdminVendorDecision;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Events\VendorAccepted;
use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdminDecidePendingBookingVendorAction
{
    public function execute(BookingVendor $bookingVendor, AdminVendorDecision $decision, string $reason): BookingAdminIntervention
    {
        Gate::authorize('booking.intervene.decide_vendor_request');
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => [__('booking::booking.intervention.validation.decision_reason')]]);
        }

        $bookingId = BookingVendor::query()->whereKey($bookingVendor->getKey())->value('booking_id');
        abort_if($bookingId === null, 404);
        $adminId = (int) auth()->id();

        return DB::transaction(function () use ($bookingVendor, $bookingId, $decision, $reason, $adminId): BookingAdminIntervention {
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $vendor = BookingVendor::query()->whereKey($bookingVendor->getKey())
                ->where('booking_id', $booking->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($booking->lifecycle_status->getValue(), [LifecycleStatus::VendorReview->value, LifecycleStatus::CustomerReview->value], true)
                || $vendor->sub_status !== VendorSubStatus::Pending) {
                throw new \DomainException(__('booking::booking.intervention.error.vendor_not_pending'));
            }
            if ($vendor->response_deadline === null || $vendor->response_deadline->isPast()) {
                throw new ResponseDeadlineExpiredException((int) $vendor->getKey());
            }

            $beforeStatus = $booking->lifecycle_status->getValue();
            $before = [
                'sub_status' => $vendor->sub_status->value,
                'response_deadline' => $vendor->response_deadline->toDateTimeString(),
                'booking_status' => $beforeStatus,
                'vendor_profile_id' => $vendor->vendor_profile_id,
                'subtotal_minor' => $vendor->subtotal_minor,
                'subtotal_currency' => $vendor->subtotal_currency,
                'delivery_fee_minor' => $vendor->delivery_fee_minor,
                'delivery_fee_currency' => $vendor->delivery_fee_currency,
                'commission_minor' => $vendor->commission_minor,
                'commission_currency' => $vendor->commission_currency,
                'vendor_payout_minor' => $vendor->vendor_payout_minor,
                'vendor_payout_currency' => $vendor->vendor_payout_currency,
            ];
            $now = now();
            $target = $decision === AdminVendorDecision::Accept ? VendorSubStatus::Accepted : VendorSubStatus::Rejected;
            $vendor->update([
                'sub_status' => $target,
                'responded_at' => $now,
                ...($decision === AdminVendorDecision::Reject ? ['rejection_reason' => ['en' => $reason, 'ar' => $reason]] : []),
            ]);
            $intervention = BookingAdminIntervention::query()->create([
                'public_id' => Str::ulid()->toBase32(),
                'booking_id' => $booking->getKey(),
                'admin_id' => $adminId,
                'intervention_type' => InterventionType::VendorDecision,
                'reason' => $reason,
                'before_state' => $before,
                'after_state' => [...$before, 'sub_status' => $target->value, 'responded_at' => $now->toDateTimeString(),
                    'decision' => $decision->value, 'actor_admin_id' => $adminId,
                    'acted_for_vendor_profile_id' => $vendor->vendor_profile_id],
            ]);
            StateTransition::query()->create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $vendor->getKey(),
                'from_state' => VendorSubStatus::Pending->value,
                'to_state' => $target->value,
                'trigger_kind' => 'admin',
                'triggered_by' => $adminId,
                'reason' => $reason,
                'context' => ['intervention_id' => $intervention->getKey(), 'booking_id' => $booking->getKey(),
                    'acted_for_vendor_profile_id' => $vendor->vendor_profile_id],
            ]);

            $bookingEvent = null;
            if ($decision === AdminVendorDecision::Accept
                && ! BookingVendor::query()->where('booking_id', $booking->getKey())
                    ->where('sub_status', '!=', VendorSubStatus::Accepted->value)->exists()) {
                $booking->update(['lifecycle_status' => ConfirmedState::class, 'confirmed_at' => $now]);
                StateTransition::query()->create([
                    'transitionable_type' => Booking::class, 'transitionable_id' => $booking->getKey(),
                    'from_state' => $beforeStatus, 'to_state' => LifecycleStatus::Confirmed->value, 'trigger_kind' => 'system',
                ]);
                $bookingEvent = new BookingConfirmed($booking->fresh());
            } elseif ($decision === AdminVendorDecision::Reject) {
                $allRejected = ! BookingVendor::query()->where('booking_id', $booking->getKey())
                    ->where('sub_status', '!=', VendorSubStatus::Rejected->value)->exists();
                $next = $allRejected ? LifecycleStatus::Cancelled : LifecycleStatus::CustomerReview;
                if ($booking->lifecycle_status->getValue() !== $next->value) {
                    $booking->update([
                        'lifecycle_status' => $allRejected ? CancelledState::class : CustomerReviewState::class,
                        ...($allRejected ? ['cancelled_at' => $now] : []),
                    ]);
                    StateTransition::query()->create([
                        'transitionable_type' => Booking::class, 'transitionable_id' => $booking->getKey(),
                        'from_state' => $beforeStatus, 'to_state' => $next->value, 'trigger_kind' => 'system',
                    ]);
                }
                if ($allRejected) {
                    $bookingEvent = new BookingCancelled($booking->fresh());
                }
            }

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(), 'auditable_type' => BookingVendor::class,
                'auditable_id' => $vendor->getKey(), 'user_id' => $adminId, 'action' => 'booking.vendor_decision',
                'changes' => json_encode(['before' => $before, 'after' => $intervention->after_state,
                    'reason' => $reason, 'acted_for_vendor_profile_id' => $vendor->vendor_profile_id], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            $updatedVendor = $vendor->fresh();
            DB::afterCommit(function () use ($decision, $updatedVendor, $bookingEvent): void {
                event($decision === AdminVendorDecision::Accept ? new VendorAccepted($updatedVendor) : new VendorRejected($updatedVendor));
                if ($bookingEvent !== null) {
                    event($bookingEvent);
                }
            });

            return $intervention;
        }, 3);
    }
}

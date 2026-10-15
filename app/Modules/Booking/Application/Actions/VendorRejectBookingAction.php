<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\VendorRejectDTO;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VendorRejectBookingAction
{
    public function execute(VendorRejectDTO $dto): BookingVendor
    {
        if ($dto->idempotencyKey !== null) {
            $cached = $this->getCachedIdempotencyResponse($dto);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Resolve the parent without locking the allocation, then constrain the locked
        // re-read so a changed relationship cannot target a different allocation.
        $bookingId = BookingVendor::query()->whereKey($dto->bookingVendorId)->value('booking_id');
        abort_if($bookingId === null, Response::HTTP_NOT_FOUND);

        return DB::transaction(function () use ($dto, $bookingId): BookingVendor {
            /** @var Booking $booking */
            $booking = Booking::query()
                ->whereKey((int) $bookingId)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var BookingVendor $bookingVendor */
            $bookingVendor = BookingVendor::query()
                ->whereKey($dto->bookingVendorId)
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $bookingVendor->vendor_profile_id !== $dto->vendorProfileId,
                Response::HTTP_FORBIDDEN
            );

            abort_if(
                $bookingVendor->sub_status !== VendorSubStatus::Pending,
                Response::HTTP_CONFLICT,
                'Booking vendor is not in pending status'
            );

            if ($bookingVendor->response_deadline !== null
                && $bookingVendor->response_deadline->isPast()) {
                throw new ResponseDeadlineExpiredException($bookingVendor->id);
            }

            abort_if(
                $booking->lifecycle_status instanceof CancelledState,
                Response::HTTP_CONFLICT,
                'Booking is cancelled',
            );

            $updateData = [
                'sub_status' => VendorSubStatus::Rejected,
                'responded_at' => now(),
            ];
            if ($dto->rejectionReason !== null) {
                $updateData['rejection_reason'] = $dto->rejectionReason;
            }
            $bookingVendor->update($updateData);

            StateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $bookingVendor->id,
                'from_state' => VendorSubStatus::Pending->value,
                'to_state' => VendorSubStatus::Rejected->value,
                'trigger_kind' => 'vendor',
                'triggered_by' => $dto->vendorProfileId,
            ]);

            $allRejected = ! BookingVendor::where('booking_id', $booking->id)
                ->where('sub_status', '!=', VendorSubStatus::Rejected->value)
                ->exists();

            $bookingCancelled = false;
            if ($allRejected) {
                $booking->update([
                    'lifecycle_status' => CancelledState::class,
                    'cancelled_at' => now(),
                ]);

                StateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => $booking->getOriginal('lifecycle_status') ?? LifecycleStatus::VendorReview->value,
                    'to_state' => LifecycleStatus::Cancelled->value,
                    'trigger_kind' => 'system',
                ]);

                $bookingCancelled = true;
            } else {
                $booking->update(['lifecycle_status' => CustomerReviewState::class]);

                StateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => LifecycleStatus::VendorReview->value,
                    'to_state' => LifecycleStatus::CustomerReview->value,
                    'trigger_kind' => 'system',
                ]);
            }

            DB::afterCommit(function () use ($bookingVendor, $booking, $bookingCancelled, $dto): void {
                event(new VendorRejected($bookingVendor));
                if ($bookingCancelled) {
                    event(new BookingCancelled($booking));
                }
                if ($dto->idempotencyKey !== null) {
                    $this->storeIdempotencyResponse($dto, $bookingVendor);
                }
            });

            return $bookingVendor;
        });
    }

    private function requestHash(VendorRejectDTO $dto): string
    {
        return hash('sha256', 'vendor.reject|'.$dto->vendorProfileId.'|'.$dto->bookingVendorId);
    }

    private function getCachedIdempotencyResponse(VendorRejectDTO $dto): ?BookingVendor
    {
        $row = DB::table('idempotency_keys')
            ->where('key', $dto->idempotencyKey)
            ->where('scope', 'http')
            ->where('scope_key', 'http')
            ->where('user_id', $dto->proposedByUserId)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        if (! hash_equals($row->request_hash, $this->requestHash($dto))) {
            abort(Response::HTTP_CONFLICT, 'Idempotency key conflict');
        }

        /** @var array<string,mixed> $body */
        $body = json_decode($row->response_body, true) ?? [];

        return BookingVendor::find((int) ($body['booking_vendor_id'] ?? 0));
    }

    private function storeIdempotencyResponse(VendorRejectDTO $dto, BookingVendor $bookingVendor): void
    {
        DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $dto->idempotencyKey,
            'scope' => 'http',
            'scope_key' => 'http',
            'user_id' => $dto->proposedByUserId,
            'route' => 'vendor.reject',
            'request_hash' => $this->requestHash($dto),
            'response_status' => Response::HTTP_OK,
            'response_body' => json_encode(['booking_vendor_id' => $bookingVendor->id]),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\VendorResponseTimedOut;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class ExpireStaleVendorReviewsAction
{
    /** @return array{bookings: int, vendors: int, customer_review: int, cancelled: int} */
    public function execute(int $limit = 100, ?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now('UTC');

        $bookingIds = Booking::query()
            ->where('lifecycle_status', LifecycleStatus::VendorReview->value)
            ->where(function ($query) use ($asOf): void {
                $query->where(function ($eventQuery) use ($asOf): void {
                    $eventQuery->whereNotNull('event_starts_at')->where('event_starts_at', '<=', $asOf);
                })->orWhereHas('vendors', function ($vendorQuery) use ($asOf): void {
                    $vendorQuery->where('sub_status', VendorSubStatus::Pending->value)
                        ->whereNotNull('response_deadline')
                        ->where('response_deadline', '<=', $asOf);
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $totals = ['bookings' => 0, 'vendors' => 0, 'customer_review' => 0, 'cancelled' => 0];

        foreach ($bookingIds as $bookingId) {
            $result = $this->expireBooking((int) $bookingId, $asOf);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $result[$key];
            }
        }

        return $totals;
    }

    /** @return array{bookings: int, vendors: int, customer_review: int, cancelled: int} */
    private function expireBooking(int $bookingId, CarbonImmutable $asOf): array
    {
        return DB::transaction(function () use ($bookingId, $asOf): array {
            /** @var Booking|null $booking */
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->first();

            if ($booking === null || $booking->lifecycle_status->getValue() !== LifecycleStatus::VendorReview->value) {
                return $this->emptyResult();
            }

            $eventStarted = $booking->event_starts_at !== null && $booking->event_starts_at->lte($asOf);

            $expiredVendors = BookingVendor::query()
                ->where('booking_id', $booking->id)
                ->where('sub_status', VendorSubStatus::Pending->value)
                ->when(! $eventStarted, fn ($query) => $query
                    ->whereNotNull('response_deadline')
                    ->where('response_deadline', '<=', $asOf))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $timedOutVendorIds = [];

            foreach ($expiredVendors as $vendor) {
                $vendor->update(['sub_status' => VendorSubStatus::TimedOut, 'responded_at' => $asOf]);

                StateTransition::query()->create([
                    'transitionable_type' => BookingVendor::class,
                    'transitionable_id' => $vendor->id,
                    'from_state' => VendorSubStatus::Pending->value,
                    'to_state' => VendorSubStatus::TimedOut->value,
                    'trigger_kind' => 'system',
                    'reason' => $eventStarted ? 'event_started' : 'response_deadline_expired',
                    'context' => [
                        'booking_public_id' => $booking->public_id,
                        'response_deadline' => $vendor->response_deadline?->utc()->toIso8601String(),
                        'evaluated_at' => $asOf->toIso8601String(),
                    ],
                ]);

                DB::table('audit_logs')->insert([
                    'public_id' => Str::ulid()->toBase32(),
                    'auditable_type' => BookingVendor::class,
                    'auditable_id' => $vendor->id,
                    'user_id' => null,
                    'action' => 'booking.vendor_response_timed_out',
                    'changes' => json_encode([
                        'before' => ['sub_status' => VendorSubStatus::Pending->value],
                        'after' => ['sub_status' => VendorSubStatus::TimedOut->value],
                        'reason' => $eventStarted ? 'event_started' : 'response_deadline_expired',
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $asOf,
                ]);

                $timedOutVendorIds[] = $vendor->id;
            }

            $result = [
                'bookings' => $timedOutVendorIds === [] ? 0 : 1,
                'vendors' => count($timedOutVendorIds),
                'customer_review' => 0,
                'cancelled' => 0,
            ];

            if ($eventStarted) {
                $this->transitionBooking($booking, LifecycleStatus::Cancelled, $asOf, 'event_started');
                $result['bookings'] = 1;
                $result['cancelled'] = 1;
                DB::afterCommit(fn () => event(new BookingCancelled($booking->refresh())));
            } elseif ($timedOutVendorIds !== [] && ! $booking->vendors()
                ->where('sub_status', VendorSubStatus::Pending->value)->exists()) {
                $this->transitionBooking($booking, LifecycleStatus::CustomerReview, $asOf, 'vendor_response_deadline_expired');
                $result['customer_review'] = 1;
            }

            if ($timedOutVendorIds !== []) {
                DB::afterCommit(fn () => event(new VendorResponseTimedOut(
                    bookingId: $booking->id,
                    bookingVendorIds: $timedOutVendorIds,
                    eventAlreadyStarted: $eventStarted,
                )));
            }

            return $result;
        });
    }

    private function transitionBooking(Booking $booking, LifecycleStatus $to, CarbonImmutable $asOf, string $reason): void
    {
        $from = $booking->lifecycle_status->getValue();

        $booking->update([
            'lifecycle_status' => match ($to) {
                LifecycleStatus::CustomerReview => CustomerReviewState::class,
                LifecycleStatus::Cancelled => CancelledState::class,
                default => throw new LogicException("Unsupported stale-review transition to {$to->value}"),
            },
            'cancelled_at' => $to === LifecycleStatus::Cancelled ? $asOf : $booking->cancelled_at,
            'cancelled_by' => $to === LifecycleStatus::Cancelled ? null : $booking->cancelled_by,
        ]);

        StateTransition::query()->create([
            'transitionable_type' => Booking::class,
            'transitionable_id' => $booking->id,
            'from_state' => $from,
            'to_state' => $to->value,
            'trigger_kind' => 'system',
            'reason' => $reason,
            'context' => ['evaluated_at' => $asOf->toIso8601String()],
        ]);
    }

    /** @return array{bookings: int, vendors: int, customer_review: int, cancelled: int} */
    private function emptyResult(): array
    {
        return ['bookings' => 0, 'vendors' => 0, 'customer_review' => 0, 'cancelled' => 0];
    }
}

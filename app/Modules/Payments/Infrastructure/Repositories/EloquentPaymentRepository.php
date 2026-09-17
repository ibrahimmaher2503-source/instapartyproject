<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Repositories;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\CapturedState;
use App\Modules\Payments\Domain\States\PaymentStatus\FailedState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class EloquentPaymentRepository
{
    public function create(array $attrs): Payment
    {
        return Payment::create($attrs);
    }

    public function findByPublicId(string $ulid): ?Payment
    {
        return Payment::query()->where('public_id', $ulid)->first();
    }

    public function findById(int $id): ?Payment
    {
        return Payment::query()->find($id);
    }

    /** @return Builder<Payment> */
    public function queryForBooking(int $bookingId): Builder
    {
        return Payment::query()->where('booking_id', $bookingId);
    }

    public function findActiveCaptureForBooking(int $bookingId): ?Payment
    {
        return Payment::query()
            ->whereState('status', CapturedState::class)
            ->where('booking_id', $bookingId)
            ->latest('id')
            ->first();
    }

    public function findByGatewayRef(string $gateway, string $ref): ?Payment
    {
        return Payment::query()->where('gateway', $gateway)->where('gateway_ref', $ref)->first();
    }

    public function markCaptured(Payment $payment, Carbon $at): void
    {
        $payment->update(['status' => CapturedState::class, 'captured_at' => $at]);
    }

    public function markFailed(Payment $payment, string $code, array $message): void
    {
        $payment->update(['status' => FailedState::class, 'failure_code' => $code, 'failure_message' => $message]);
    }
}

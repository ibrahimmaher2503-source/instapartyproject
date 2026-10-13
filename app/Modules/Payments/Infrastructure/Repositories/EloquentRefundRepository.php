<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Repositories;

use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Refund;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class EloquentRefundRepository
{
    public function create(
        InitiateRefundDto $dto,
        int $amountMinor,
        string $amountCurrency,
        ?string $idempotencyKey = null,
    ): Refund {
        return Refund::create([
            'public_id' => (string) Str::ulid(),
            'payment_id' => $dto->paymentId,
            'booking_id' => $dto->bookingId,
            'amount_minor' => $amountMinor,
            'amount_currency' => $amountCurrency,
            'reason_code' => $dto->reasonCode,
            'reason_notes' => $dto->reasonNotes,
            'status' => RefundStatus::Pending,
            'initiated_by' => $dto->initiatedBy,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /** @param list<string> $idempotencyKeyPrefixes */
    public function findByIdempotencyKeyPrefixesForBooking(int $bookingId, array $idempotencyKeyPrefixes): ?Refund
    {
        if ($idempotencyKeyPrefixes === []) {
            return null;
        }

        return Refund::query()
            ->where('booking_id', $bookingId)
            ->where(function (Builder $query) use ($idempotencyKeyPrefixes): void {
                foreach ($idempotencyKeyPrefixes as $prefix) {
                    $query->orWhere('idempotency_key', 'like', $prefix.'%');
                }
            })
            ->first();
    }

    public function markProcessing(Refund $refund): void
    {
        $refund->update(['status' => RefundStatus::Processing]);
    }

    public function markCompleted(Refund $refund, string $gatewayRef, Carbon $at): void
    {
        $refund->update(['status' => RefundStatus::Completed, 'gateway_ref' => $gatewayRef, 'processed_at' => $at]);
    }

    public function markFailed(Refund $refund, string $message): void
    {
        $refund->update(['status' => RefundStatus::Failed, 'reason_notes' => ['en' => $message, 'ar' => $message]]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Repositories;

use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Settlement\Application\DTOs\PaymentSnapshotDto;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EloquentSettlementPaymentReader implements SettlementPaymentReader
{
    public function findById(int $id): ?PaymentSnapshotDto
    {
        $row = DB::table('payments')->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        return new PaymentSnapshotDto(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            bookingId: (int) $row->booking_id,
            amountMinor: (int) $row->amount_minor,
            currency: (string) $row->amount_currency,
            status: PaymentStatus::from((string) $row->status),
            capturedAt: Carbon::parse($row->captured_at),
        );
    }

    public function findRefundById(int $id): ?RefundSnapshotDto
    {
        $row = DB::table('refunds')->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        // Refund model has: id, public_id, payment_id, booking_id, amount_minor,
        // amount_currency, reason_code, reason_notes, gateway_ref, status, initiated_by, processed_at
        // booking_item_id is not on the refund — it's a booking-level refund in Phase 1.
        return $this->refundRowToDto($row);
    }

    /**
     * @return list<RefundSnapshotDto>
     */
    public function completedRefundsForPayment(int $paymentId): array
    {
        return DB::table('refunds')
            ->where('payment_id', $paymentId)
            ->where('status', RefundStatus::Completed->value)
            ->orderByRaw('COALESCE(processed_at, updated_at) ASC')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): RefundSnapshotDto => $this->refundRowToDto($row))
            ->all();
    }

    private function refundRowToDto(object $row): RefundSnapshotDto
    {
        // Refund model has: id, public_id, payment_id, booking_id, amount_minor,
        // amount_currency, reason_code, reason_notes, gateway_ref, status, initiated_by, processed_at
        // booking_item_id is not on the refund — it's a booking-level refund in Phase 1.
        $completedAt = $row->processed_at
            ? Carbon::parse($row->processed_at)
            : Carbon::parse($row->updated_at ?? $row->created_at ?? now());

        return new RefundSnapshotDto(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            paymentId: (int) $row->payment_id,
            bookingItemId: null,
            amountMinor: (int) $row->amount_minor,
            currency: (string) $row->amount_currency,
            completedAt: $completedAt,
        );
    }
}

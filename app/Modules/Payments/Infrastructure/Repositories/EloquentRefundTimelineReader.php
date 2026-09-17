<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Repositories;

use App\Modules\Payments\Application\DTOs\RefundTimelineEntryDto;
use App\Modules\Payments\Domain\Contracts\RefundTimelineReader;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Refund;
use DateTimeImmutable;
use Illuminate\Support\Collection;

class EloquentRefundTimelineReader implements RefundTimelineReader
{
    public function findByBookingId(int $bookingId): Collection
    {
        return Refund::query()
            ->where('booking_id', $bookingId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Refund $refund): RefundTimelineEntryDto => new RefundTimelineEntryDto(
                publicId: $refund->public_id,
                amountMinor: (int) $refund->amount_minor,
                currency: (string) $refund->amount_currency,
                status: $refund->status instanceof RefundStatus
                    ? $refund->status
                    : RefundStatus::from((string) $refund->status),
                reasonCode: $refund->reason_code instanceof RefundReasonCode
                    ? $refund->reason_code
                    : RefundReasonCode::from((string) $refund->reason_code),
                initiatedAt: DateTimeImmutable::createFromInterface($refund->created_at->toDateTimeImmutable()),
                processedAt: $refund->processed_at
                    ? DateTimeImmutable::createFromInterface($refund->processed_at->toDateTimeImmutable())
                    : null,
            ))
            ->values();
    }
}

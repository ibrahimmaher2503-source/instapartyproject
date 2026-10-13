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
        return $this->findByBookingIds([$bookingId])[$bookingId] ?? collect();
    }

    /**
     * @param  array<int, int>  $bookingIds
     * @return array<int, Collection<int, RefundTimelineEntryDto>>
     */
    public function findByBookingIds(array $bookingIds): array
    {
        $bookingIds = array_values(array_unique(array_filter(
            array_map('intval', $bookingIds),
            static fn (int $bookingId): bool => $bookingId > 0,
        )));

        if ($bookingIds === []) {
            return [];
        }

        return Refund::query()
            ->whereIn('booking_id', $bookingIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('booking_id')
            ->map(fn (Collection $bookingRefunds): Collection => $bookingRefunds
                ->map(fn (Refund $refund): RefundTimelineEntryDto => $this->toDto($refund))
                ->values())
            ->all();
    }

    private function toDto(Refund $refund): RefundTimelineEntryDto
    {
        return new RefundTimelineEntryDto(
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
        );
    }
}

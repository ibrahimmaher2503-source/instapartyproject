<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\DTOs;

use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use DateTimeImmutable;

final readonly class RefundTimelineEntryDto
{
    public function __construct(
        public string $publicId,
        public int $amountMinor,
        public string $currency,
        public RefundStatus $status,
        public RefundReasonCode $reasonCode,
        public DateTimeImmutable $initiatedAt,
        public ?DateTimeImmutable $processedAt,
    ) {}
}

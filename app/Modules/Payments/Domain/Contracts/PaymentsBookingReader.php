<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Payments\Application\DTOs\PaymentBookingItemReadDto;
use App\Modules\Payments\Application\DTOs\PaymentBookingReadDto;
use Brick\Money\Money;

interface PaymentsBookingReader
{
    public function findByPublicId(string $ulid): ?PaymentBookingReadDto;

    public function refundCashBudget(int $bookingId, bool $lock = false): ?Money;

    /** @return array<int, PaymentBookingItemReadDto> */
    public function itemsFor(int $bookingId): array;

    /** @return array<int> */
    public function staleHoldBookingIds(): array;
}

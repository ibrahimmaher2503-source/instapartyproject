<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Services;

use App\Modules\Booking\Application\DTOs\CoverageMinimumStatusDTO;

/**
 * Per-request memoization cache for CoverageMinimumStatusService results.
 * Bound as `scoped` so it resets between HTTP requests (including in tests).
 */
final class BookingVendorStatusMapCache
{
    /** @var array<int, array<int, array{status: CoverageMinimumStatusDTO|null, reason_code: string|null}>> */
    private array $data = [];

    public function has(int $bookingId): bool
    {
        return isset($this->data[$bookingId]);
    }

    /** @return array<int, array{status: CoverageMinimumStatusDTO|null, reason_code: string|null}> */
    public function get(int $bookingId): array
    {
        return $this->data[$bookingId];
    }

    /** @param array<int, array{status: CoverageMinimumStatusDTO|null, reason_code: string|null}> $statusMap */
    public function set(int $bookingId, array $statusMap): void
    {
        $this->data[$bookingId] = $statusMap;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Contracts;

use App\Modules\Booking\Application\DTOs\CoverageAreaDTO;

interface CoverageAreaResolver
{
    /**
     * Batch-resolve coverage rows for one delivery city.
     *
     * @param  int  $cityId  The delivery city for the booking under evaluation.
     * @param  array<int>  $vendorProfileIds  Distinct vendor_profile_ids participating in the booking.
     * @return array<int, CoverageAreaDTO|null>
     *                                          Keyed by vendor_profile_id. Value is the DTO when a row exists, or null when no
     *                                          vendor_coverage_areas row exists for (vendor_profile_id, city_id).
     *                                          Every input id MUST appear as a key in the returned array (use null for misses).
     */
    public function resolveForCity(int $cityId, array $vendorProfileIds): array;
}

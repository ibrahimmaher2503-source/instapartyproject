<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Contracts;

use App\Modules\Booking\Application\DTOs\ServicePackageReadDTO;
use App\Modules\Booking\Application\DTOs\ServiceReadDTO;
use App\Modules\Booking\Application\DTOs\ServiceThemeReadDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Carbon\Carbon;

interface CatalogServiceReader
{
    public function findPublishedById(int $id): ?ServiceReadDTO;

    public function findBookingServiceById(int $id): ?ServiceReadDTO;

    /**
     * @param  list<string>  $excludedPublicIds
     * @return list<ServiceReadDTO>
     */
    public function findEligibleReplacementServices(
        int $vendorProfileId,
        int $categoryId,
        ProductType $productType,
        int $unitPriceMinor,
        string $currency,
        int $quantity,
        ?Carbon $startsAt,
        ?Carbon $endsAt,
        array $excludedPublicIds = [],
    ): array;

    /** @param list<string> $excludedPublicIds
     * @return list<ServiceReadDTO>
     */
    public function findEligibleReplacementServicesForPackage(
        int $vendorProfileId,
        int $categoryId,
        ProductType $productType,
        int $quantity,
        ?Carbon $startsAt,
        ?Carbon $endsAt,
        array $excludedPublicIds = [],
    ): array;

    public function resolvePublicId(string $publicId): ?int;

    public function isServiceEligibleForOccasion(int $serviceId, int $occasionId): bool;

    public function findActiveThemeForService(string $servicePublicId, string $themePublicId): ?ServiceThemeReadDTO;

    public function findActivePackageForService(string $servicePublicId, string $packagePublicId, bool $lockForUpdate = false): ?ServicePackageReadDTO;

    public function findEquivalentActivePackageForService(string $servicePublicId, string $code, int $priceMinor, string $currency): ?ServicePackageReadDTO;

    public function hasRentalAvailabilityBlock(int $serviceId, Carbon $startsAt, Carbon $endsAt): bool;
}

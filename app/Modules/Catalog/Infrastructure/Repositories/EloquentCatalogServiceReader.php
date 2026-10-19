<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Repositories;

use App\Modules\Booking\Application\DTOs\ServicePackageReadDTO;
use App\Modules\Booking\Application\DTOs\ServiceReadDTO;
use App\Modules\Booking\Application\DTOs\ServiceThemeReadDTO;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Catalog\Domain\Models\ServicePackage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentCatalogServiceReader implements CatalogServiceReader
{
    public function findPublishedById(int $id): ?ServiceReadDTO
    {
        $row = DB::table('services')
            ->where('id', $id)
            ->where('status', ServiceStatus::Published->value)
            ->when(
                ! config('catalog.digital_services_enabled', true),
                fn ($query) => $query->where('product_type', '!=', ProductType::Digital->value),
            )
            ->whereNull('deleted_at')
            ->first();

        if ($row === null) {
            return null;
        }

        $stockQuantity = null;
        $rentalDuration = null;
        $isMadeToOrder = false;
        $leadTimeHours = null;
        if ($row->product_type === 'sale') {
            $detail = DB::table('service_sale_details')->where('service_id', $id)->first();
            $stockQuantity = $detail?->stock_quantity;
            $isMadeToOrder = (bool) ($detail?->is_made_to_order ?? false);
            $leadTimeHours = $detail?->lead_time_hours;
        } elseif ($row->product_type === ProductType::Rental->value) {
            $rentalDuration = DB::table('service_rental_details')->where('service_id', $id)->value('default_rental_duration_hours');
        }

        $name = is_string($row->name) ? json_decode($row->name, true) : (array) $row->name;

        return new ServiceReadDTO(
            id: $row->id,
            publicId: $row->public_id,
            vendorProfileId: $row->vendor_profile_id,
            categoryId: $row->category_id,
            productType: ProductType::from($row->product_type),
            nameEn: $name['en'] ?? '',
            nameAr: $name['ar'] ?? '',
            basePriceMinor: (int) $row->base_price_minor,
            basePriceCurrency: $row->base_price_currency ?? 'EGP',
            stockQuantity: $stockQuantity !== null ? (int) $stockQuantity : null,
            defaultRentalDurationHours: $rentalDuration !== null ? (int) $rentalDuration : null,
            isMadeToOrder: $isMadeToOrder,
            leadTimeHours: $leadTimeHours !== null ? (int) $leadTimeHours : null,
        );
    }

    public function findBookingServiceById(int $id): ?ServiceReadDTO
    {
        $row = DB::table('services')->where('id', $id)->first([
            'id', 'public_id', 'vendor_profile_id', 'category_id', 'product_type', 'name',
            'base_price_minor', 'base_price_currency',
        ]);

        return $row === null ? null : $this->readServiceDTO($row);
    }

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
    ): array {
        if ($quantity < 1 || ($productType === ProductType::Rental
            && ($startsAt === null || $endsAt === null || $endsAt->lessThanOrEqualTo($startsAt)))) {
            return [];
        }

        if ($productType === ProductType::Digital && ! config('catalog.digital_services_enabled', true)) {
            return [];
        }

        $services = DB::table('services')
            ->join('vendor_profiles', 'vendor_profiles.id', '=', 'services.vendor_profile_id')
            ->where('services.vendor_profile_id', $vendorProfileId)
            ->where('vendor_profiles.approval_status', 'approved')
            ->whereNull('vendor_profiles.deleted_at')
            ->where('services.status', ServiceStatus::Published->value)
            ->whereNull('services.deleted_at')
            ->where('services.category_id', $categoryId)
            ->where('services.product_type', $productType->value)
            ->where('services.base_price_minor', $unitPriceMinor)
            ->where('services.base_price_currency', $currency)
            ->when($excludedPublicIds !== [], fn ($query) => $query->whereNotIn('services.public_id', $excludedPublicIds))
            ->orderBy('services.id')
            ->get(['services.id', 'services.public_id']);

        $eligible = [];
        foreach ($services as $serviceRow) {
            if ($productType === ProductType::Rental) {
                $blocked = DB::table('service_availability_blocks')
                    ->where('service_id', $serviceRow->id)
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->exists();
                $reserved = DB::table('service_inventory_reservations')
                    ->where('service_id', $serviceRow->id)
                    ->where(fn ($query) => $query
                        ->where('status', 'confirmed')
                        ->orWhere(fn ($held) => $held
                            ->where('status', 'held')
                            ->where('expires_at', '>', now()))
                    )
                    ->where('reserved_starts_at', '<', $endsAt)
                    ->where('reserved_ends_at', '>', $startsAt)
                    ->exists();
                if ($blocked || $reserved) {
                    continue;
                }
            }

            $service = $this->findPublishedById((int) $serviceRow->id);
            if ($service === null) {
                continue;
            }

            if ($productType === ProductType::Sale && $service->stockQuantity !== null) {
                $reservedQuantity = (int) DB::table('service_inventory_reservations')
                    ->where('service_id', $service->id)
                    ->where(fn ($query) => $query
                        ->where('status', 'confirmed')
                        ->orWhere(fn ($held) => $held
                            ->where('status', 'held')
                            ->where('expires_at', '>', now()))
                    )
                    ->sum('quantity');
                if ($service->stockQuantity - $reservedQuantity < $quantity) {
                    continue;
                }
            }

            $eligible[] = $service;
        }

        return $eligible;
    }

    /**
     * @param  list<string>  $excludedPublicIds
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
    ): array {
        if ($quantity < 1 || ($productType === ProductType::Rental
            && ($startsAt === null || $endsAt === null || $endsAt->lessThanOrEqualTo($startsAt)))) {
            return [];
        }

        if ($productType === ProductType::Digital && ! config('catalog.digital_services_enabled', true)) {
            return [];
        }

        $services = DB::table('services')
            ->join('vendor_profiles', 'vendor_profiles.id', '=', 'services.vendor_profile_id')
            ->where('services.vendor_profile_id', $vendorProfileId)
            ->where('vendor_profiles.approval_status', 'approved')
            ->whereNull('vendor_profiles.deleted_at')
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('vendor_approved_product_types')
                ->whereColumn('vendor_approved_product_types.vendor_profile_id', 'vendor_profiles.id')
                ->where('vendor_approved_product_types.product_type', $productType->value)
                ->whereNull('vendor_approved_product_types.revoked_at'))
            ->where('services.status', ServiceStatus::Published->value)
            ->whereNull('services.deleted_at')
            ->where('services.category_id', $categoryId)
            ->where('services.product_type', $productType->value)
            ->when($excludedPublicIds !== [], fn ($query) => $query->whereNotIn('services.public_id', $excludedPublicIds))
            ->orderBy('services.id')
            ->get(['services.id', 'services.public_id']);

        $eligible = [];
        foreach ($services as $serviceRow) {
            if ($productType === ProductType::Rental) {
                $blocked = DB::table('service_availability_blocks')
                    ->where('service_id', $serviceRow->id)
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->exists();
                $reserved = DB::table('service_inventory_reservations')
                    ->where('service_id', $serviceRow->id)
                    ->where(fn ($query) => $query
                        ->where('status', 'confirmed')
                        ->orWhere(fn ($held) => $held
                            ->where('status', 'held')
                            ->where('expires_at', '>', now()))
                    )
                    ->where('reserved_starts_at', '<', $endsAt)
                    ->where('reserved_ends_at', '>', $startsAt)
                    ->exists();
                if ($blocked || $reserved) {
                    continue;
                }
            }

            $service = $this->findPublishedById((int) $serviceRow->id);
            if ($service === null) {
                continue;
            }

            if ($productType === ProductType::Sale && $service->stockQuantity !== null) {
                $reservedQuantity = (int) DB::table('service_inventory_reservations')
                    ->where('service_id', $service->id)
                    ->where(fn ($query) => $query
                        ->where('status', 'confirmed')
                        ->orWhere(fn ($held) => $held
                            ->where('status', 'held')
                            ->where('expires_at', '>', now()))
                    )
                    ->sum('quantity');
                if ($service->stockQuantity - $reservedQuantity < $quantity) {
                    continue;
                }
            }

            $eligible[] = $service;
        }

        return $eligible;
    }

    public function resolvePublicId(string $publicId): ?int
    {
        $row = DB::table('services')->where('public_id', $publicId)->whereNull('deleted_at')->first(['id']);

        return $row?->id;
    }

    public function isServiceEligibleForOccasion(int $serviceId, int $occasionId): bool
    {
        return Service::query()
            ->whereKey($serviceId)
            ->whereHas('category.occasions', fn (Builder $occasions) => $occasions
                ->whereKey($occasionId)
                ->where('occasions.is_active', true))
            ->exists();
    }

    public function findActiveThemeForService(string $servicePublicId, string $themePublicId): ?ServiceThemeReadDTO
    {
        $row = DB::table('service_themes')
            ->join('service_themes_pivot', 'service_themes_pivot.service_theme_id', '=', 'service_themes.id')
            ->join('services', 'services.id', '=', 'service_themes_pivot.service_id')
            ->where('services.public_id', $servicePublicId)
            ->where('services.status', ServiceStatus::Published->value)
            ->whereNull('services.deleted_at')
            ->where('service_themes.public_id', $themePublicId)
            ->where('service_themes.is_active', true)
            ->when(
                ! config('catalog.digital_services_enabled', true),
                fn ($query) => $query->where('services.product_type', '!=', ProductType::Digital->value),
            )
            ->first([
                'service_themes.public_id',
                'service_themes.code',
                'service_themes.name',
            ]);

        if ($row === null) {
            return null;
        }

        $name = is_string($row->name) ? json_decode($row->name, true) : (array) $row->name;

        return new ServiceThemeReadDTO(
            publicId: $row->public_id,
            code: $row->code,
            nameEn: $name['en'] ?? '',
            nameAr: $name['ar'] ?? '',
        );
    }

    public function findActivePackageForService(string $servicePublicId, string $packagePublicId, bool $lockForUpdate = false): ?ServicePackageReadDTO
    {
        $query = ServicePackage::query()
            ->available()
            ->where('public_id', $packagePublicId)
            ->where('is_active', true)
            ->whereHas('service', fn (Builder $service) => $service
                ->displayable()
                ->where('public_id', $servicePublicId)
                ->whereColumn('services.base_price_currency', 'service_packages.currency'))
            ->with(['components', 'media']);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $package = $query->first();
        if ($package === null) {
            return null;
        }

        return $this->packageReadDTO($package);
    }

    public function findEquivalentActivePackageForService(string $servicePublicId, string $code, int $priceMinor, string $currency): ?ServicePackageReadDTO
    {
        $package = ServicePackage::query()
            ->available()
            ->where('code', $code)
            ->where('price_minor', $priceMinor)
            ->where('currency', $currency)
            ->where('is_active', true)
            ->whereHas('service', fn (Builder $service) => $service
                ->displayable()
                ->where('public_id', $servicePublicId)
                ->whereColumn('services.base_price_currency', 'service_packages.currency'))
            ->with(['components', 'media'])
            ->first();

        return $package === null ? null : $this->packageReadDTO($package);
    }

    private function packageReadDTO(ServicePackage $package): ServicePackageReadDTO
    {
        $localized = static fn (string $field): array => [
            'en' => $package->getTranslation($field, 'en', useFallbackLocale: false),
            'ar' => $package->getTranslation($field, 'ar', useFallbackLocale: false),
        ];

        return new ServicePackageReadDTO(
            publicId: $package->public_id,
            code: $package->code,
            name: $localized('name'),
            description: $localized('description'),
            priceMinor: $package->price_minor,
            currency: $package->currency,
            components: $package->components->map(fn ($component): array => [
                'public_id' => $component->public_id,
                'name' => [
                    'en' => $component->getTranslation('name', 'en', useFallbackLocale: false),
                    'ar' => $component->getTranslation('name', 'ar', useFallbackLocale: false),
                ],
                'description' => [
                    'en' => $component->getTranslation('description', 'en', useFallbackLocale: false),
                    'ar' => $component->getTranslation('description', 'ar', useFallbackLocale: false),
                ],
                'quantity' => $component->quantity,
                'sort_order' => $component->sort_order,
            ])->values()->all(),
            media: $package->getMedia('gallery')->map(fn ($item): array => [
                'public_id' => $item->public_id,
                'thumb' => $item->getUrl('thumb'),
                'medium' => $item->getUrl('medium'),
                'original' => $item->getUrl(),
            ])->values()->all(),
        );
    }

    public function hasRentalAvailabilityBlock(int $serviceId, Carbon $startsAt, Carbon $endsAt): bool
    {
        return ServiceAvailabilityBlock::query()
            ->where('service_id', $serviceId)
            ->overlapping($startsAt, $endsAt)
            ->exists();
    }

    /** @param object{ id: int, public_id: string, vendor_profile_id: int, category_id: int, product_type: string, name: mixed, base_price_minor: int, base_price_currency: string|null } $row */
    private function readServiceDTO(object $row): ServiceReadDTO
    {
        $stockQuantity = null;
        $rentalDuration = null;
        $isMadeToOrder = false;
        $leadTimeHours = null;
        if ($row->product_type === ProductType::Sale->value) {
            $detail = DB::table('service_sale_details')->where('service_id', $row->id)->first();
            $stockQuantity = $detail?->stock_quantity;
            $isMadeToOrder = (bool) ($detail?->is_made_to_order ?? false);
            $leadTimeHours = $detail?->lead_time_hours;
        } elseif ($row->product_type === ProductType::Rental->value) {
            $rentalDuration = DB::table('service_rental_details')->where('service_id', $row->id)->value('default_rental_duration_hours');
        }

        $name = is_string($row->name) ? json_decode($row->name, true) : (array) $row->name;

        return new ServiceReadDTO(
            id: (int) $row->id,
            publicId: (string) $row->public_id,
            vendorProfileId: (int) $row->vendor_profile_id,
            categoryId: (int) $row->category_id,
            productType: ProductType::from($row->product_type),
            nameEn: $name['en'] ?? '',
            nameAr: $name['ar'] ?? '',
            basePriceMinor: (int) $row->base_price_minor,
            basePriceCurrency: $row->base_price_currency ?? 'EGP',
            stockQuantity: $stockQuantity !== null ? (int) $stockQuantity : null,
            defaultRentalDurationHours: $rentalDuration !== null ? (int) $rentalDuration : null,
            isMadeToOrder: $isMadeToOrder,
            leadTimeHours: $leadTimeHours !== null ? (int) $leadTimeHours : null,
        );
    }
}

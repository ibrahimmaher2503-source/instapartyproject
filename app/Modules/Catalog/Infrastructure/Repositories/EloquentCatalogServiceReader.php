<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Repositories;

use App\Modules\Booking\Application\DTOs\ServiceReadDTO;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
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
        if ($row->product_type === 'sale') {
            $detail = DB::table('service_sale_details')->where('service_id', $id)->first();
            $stockQuantity = $detail?->stock_quantity;
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
        );
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
}

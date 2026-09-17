<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources\Concerns;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Support\Facades\Storage;

/**
 * Single source of truth for the service JSON contract shared across the
 * customer search, customer detail, and vendor service resources.
 *
 * Mirrors the frontend `ServiceSummary` / `ServiceDetail` types
 * (apps/frontend/src/types/api.ts). Locale conversion happens here, never in
 * controllers or actions (CLAUDE.md §9 + modules.md).
 */
trait BuildsServiceContract
{
    /**
     * Canonical `ServiceSummary` shape.
     *
     * @return array<string, mixed>
     */
    protected function serviceSummary(Service $service, string $locale): array
    {
        return [
            'public_id' => $service->public_id,
            'name' => $service->getTranslation('name', $locale, useFallbackLocale: true),
            'short_description' => $service->getTranslation('short_description', $locale, useFallbackLocale: true) ?: null,
            'product_type' => $service->product_type->value,
            // Derived availability. Single source of truth is the published
            // lifecycle state set by the admin (PublishServiceAction); there is
            // no independent is_active column on services.
            'is_active' => $service->status?->getValue() === ServiceStatus::Published->value,
            'category' => $this->serviceCategoryBlock($service, $locale),
            'vendor' => $this->serviceVendorBlock($service, $locale),
            'cover_image_url' => rescue(fn () => $service->getFirstMediaUrl('gallery', 'thumb') ?: null, null, report: false),
            'price_from_minor' => (int) $service->base_price_minor,
            'currency' => $service->base_price_currency,
            'rating_avg' => $service->rating_avg !== null ? (float) $service->rating_avg : null,
            'rating_count' => (int) ($service->rating_count ?? 0),
            'service_area_hint' => $this->serviceAreaHint($service, $locale),
        ];
    }

    /**
     * Per-type detail block exposed to customers. One `match($enum)` — never
     * an if/elseif chain (CLAUDE.md §8). Vendor resources extend this with
     * write-only fields on top of the summary.
     *
     * @return array<string, mixed>
     */
    protected function serviceTypeBlock(Service $service): array
    {
        return match ($service->product_type) {
            ProductType::Rental => [
                'rental' => [
                    'requires_electricity' => (bool) $service->rentalDetail?->requires_electricity,
                    'requires_outdoor_space' => (bool) $service->rentalDetail?->requires_outdoor_space,
                    'default_rental_duration_hours' => $service->rentalDetail?->default_rental_duration_hours,
                    'setup_time_minutes' => $service->rentalDetail?->setup_time_minutes,
                    'security_deposit_minor' => $service->rentalDetail?->security_deposit_minor,
                ],
            ],
            ProductType::Sale => [
                'sale' => [
                    'is_perishable' => (bool) $service->saleDetail?->is_perishable,
                    'is_made_to_order' => (bool) $service->saleDetail?->is_made_to_order,
                    'lead_time_hours' => $service->saleDetail?->lead_time_hours,
                    'stock_quantity' => $service->saleDetail?->stock_quantity,
                ],
            ],
            ProductType::Digital => [
                'digital' => [
                    'delivery_method' => $service->digitalDetail?->delivery_method,
                    'has_expiry' => (bool) $service->digitalDetail?->has_expiry,
                    'expiry_days_after_purchase' => $service->digitalDetail?->expiry_days_after_purchase,
                    'is_refundable_after_delivery' => (bool) $service->digitalDetail?->is_refundable_after_delivery,
                ],
            ],
        };
    }

    /**
     * @return array{public_id: string, name: ?string}|null
     */
    protected function serviceCategoryBlock(Service $service, string $locale): ?array
    {
        $category = $service->category;

        if ($category === null) {
            return null;
        }

        return [
            'public_id' => $category->public_id,
            'name' => $category->getTranslation('name', $locale, useFallbackLocale: true),
        ];
    }

    /**
     * @return array{public_id: string, business_name: ?string, logo_url: ?string}|null
     */
    protected function serviceVendorBlock(Service $service, string $locale): ?array
    {
        $vendor = $service->vendor;

        if ($vendor === null) {
            return null;
        }

        return [
            'public_id' => $vendor->public_id,
            'business_name' => $vendor->getTranslation('business_name', $locale, useFallbackLocale: true),
            'logo_url' => $vendor->logo_path ? Storage::disk('public')->url($vendor->logo_path) : null,
        ];
    }

    /**
     * Short, human-readable location hint derived from the vendor's primary
     * city (falling back to governorate). Rendered on service cards.
     */
    protected function serviceAreaHint(Service $service, string $locale): ?string
    {
        $vendor = $service->vendor;

        if ($vendor === null) {
            return null;
        }

        foreach ([$vendor->primaryCity, $vendor->primaryGovernorate] as $place) {
            if ($place === null) {
                continue;
            }

            $name = $place->getTranslation('name', $locale, useFallbackLocale: true);

            if (! empty($name)) {
                return $name;
            }
        }

        return null;
    }
}

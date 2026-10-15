<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Catalog\Http\Resources\Concerns\BuildsServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing service detail. Canonical summary + long description +
 * gallery + per-type block. Frontend type: `ServiceDetail`.
 *
 * @response {
 *   "data": {
 *     "public_id": "01HXY...",
 *     "name": "Party setup",
 *     "themes": [{"public_id": "01HXZ...", "code": "garden", "name": "Garden", "icon_url": null}],
 *     "packages": [{
 *       "public_id": "01HXA...",
 *       "code": "premium-set",
 *       "name": "Premium Set",
 *       "description": "A bilingual package.",
 *       "price_minor": 7800,
 *       "currency": "EGP",
 *       "components": [{"public_id": "01HXB...", "name": "Chair", "description": "One chair", "quantity": 4}],
 *       "gallery": [{"public_id": "01HXC...", "thumb": "https://cdn.example.test/thumb.webp", "medium": "https://cdn.example.test/medium.webp"}]
 *     }]
 *   }
 * }
 *
 * @mixin Service
 */
class CustomerServiceDetailResource extends JsonResource
{
    use BuildsServiceContract;

    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return array_merge(
            $this->serviceSummary($this->resource, $locale),
            [
                'description' => $this->getTranslation('long_description', $locale, useFallbackLocale: true) ?: null,
                'gallery' => $this->getMedia('gallery')->map(fn ($m): array => [
                    'public_id' => $m->public_id,
                    'thumb' => $m->getUrl('thumb'),
                    'medium' => $m->getUrl('medium'),
                    'large' => $m->getUrl('large'),
                    'original' => $m->getUrl(),
                    'display_order' => $m->order_column,
                ])->values()->all(),
                'themes' => $this->resource->themes
                    ->where('is_active', true)
                    ->map(fn (ServiceTheme $theme): array => [
                        'public_id' => $theme->public_id,
                        'code' => $theme->code,
                        'name' => $theme->getTranslation('name', $locale, useFallbackLocale: true),
                        'icon_url' => $theme->icon_path ?: null,
                    ])
                    ->values()
                    ->all(),
                'packages' => $this->resource->packages
                    ->where('currency', $this->resource->base_price_currency)
                    ->map(fn ($package): array => [
                        'public_id' => $package->public_id,
                        'code' => $package->code,
                        'name' => $package->getTranslation('name', $locale, useFallbackLocale: true),
                        'description' => $package->getTranslation('description', $locale, useFallbackLocale: true),
                        'price_minor' => $package->price_minor,
                        'currency' => $package->currency,
                        'components' => $package->components->map(fn ($component): array => [
                            'public_id' => $component->public_id,
                            'name' => $component->getTranslation('name', $locale, useFallbackLocale: true),
                            'description' => $component->getTranslation('description', $locale, useFallbackLocale: true),
                            'quantity' => $component->quantity,
                        ])->values()->all(),
                        'gallery' => $package->getMedia('gallery')->map(fn ($media): array => [
                            'public_id' => $media->public_id,
                            'thumb' => $media->getUrl('thumb'),
                            'medium' => $media->getUrl('medium'),
                        ])->values()->all(),
                    ])->values()->all(),
            ],
            $this->serviceTypeBlock($this->resource),
        );
    }
}

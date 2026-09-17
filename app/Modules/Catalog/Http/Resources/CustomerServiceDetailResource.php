<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Http\Resources\Concerns\BuildsServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing service detail. Canonical summary + long description +
 * gallery + per-type block. Frontend type: `ServiceDetail`.
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
            ],
            $this->serviceTypeBlock($this->resource),
        );
    }
}

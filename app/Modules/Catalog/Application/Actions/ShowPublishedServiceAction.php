<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShowPublishedServiceAction
{
    /**
     * Resolve a published service by its public ULID with every relationship
     * the customer detail contract needs eager-loaded (vendor location for the
     * area hint, category, and all three detail tables).
     */
    public function execute(string $publicId): Service
    {
        return Service::query()
            ->displayable()
            ->with([
                'media',
                'category',
                'vendor.primaryCity.governorate',
                'vendor.primaryGovernorate', 'vendor.coverageAreas.city.governorate',
                'rentalDetail',
                'saleDetail',
                'digitalDetail',
                'themes' => fn (BelongsToMany $themes) => $themes->where('is_active', true),
                'packages' => fn (HasMany $packages) => $packages
                    ->available()
                    ->orderBy('code'),
                'packages.components',
                'packages.media',
            ])
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}

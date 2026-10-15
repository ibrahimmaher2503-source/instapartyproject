<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServicePackage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListPublicServicePackagesAction
{
    /** @return Collection<int, ServicePackage> */
    public function execute(string $servicePublicId): Collection
    {
        $service = Service::query()
            ->displayable()
            ->with([
                'packages' => fn (HasMany $packages) => $packages
                    ->available()
                    ->orderBy('code'),
                'packages.components',
                'packages.media',
            ])
            ->where('public_id', $servicePublicId)
            ->first();

        if ($service === null) {
            return new Collection;
        }

        return $service->packages->where('currency', $service->base_price_currency)->values();
    }
}

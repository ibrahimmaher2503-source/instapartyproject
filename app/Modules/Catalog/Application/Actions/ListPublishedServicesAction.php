<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Database\Eloquent\Collection;

final class ListPublishedServicesAction
{
    /**
     * Resolve the featured services in the same order selected by the CMS.
     *
     * @param  array<int, string>  $publicIds
     * @return Collection<int, Service>
     */
    public function execute(array $publicIds): Collection
    {
        if ($publicIds === []) {
            return new Collection;
        }

        $services = Service::query()
            ->published()
            ->with(['media', 'vendor.primaryCity', 'vendor.primaryGovernorate', 'category'])
            ->whereIn('public_id', $publicIds)
            ->get()
            ->keyBy('public_id');

        return new Collection(
            array_values(array_filter(
                array_map(static fn (string $publicId): ?Service => $services->get($publicId), $publicIds),
            )),
        );
    }
}

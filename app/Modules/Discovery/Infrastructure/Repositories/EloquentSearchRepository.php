<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Infrastructure\Repositories;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Discovery\Domain\Contracts\SearchRepository;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\VendorProfile;

class EloquentSearchRepository implements SearchRepository
{
    public function resolveOccasionId(string $code): ?int
    {
        return Occasion::where('code', $code)->value('id');
    }

    public function resolveVendorId(string $publicId): ?int
    {
        return VendorProfile::where('public_id', $publicId)->value('id');
    }

    public function resolveCategoryId(string $publicId): ?int
    {
        return Category::where('code', $publicId)->value('id');
    }

    public function resolveCategoryIds(string $code): array
    {
        $root = Category::where('code', $code)->first(['id']);

        if ($root === null) {
            return [];
        }

        $children = Category::where('parent_id', $root->id)->pluck('id');

        return array_values(array_merge([$root->id], $children->all()));
    }

    public function resolveCityId(string $publicId): ?int
    {
        return City::where('public_id', $publicId)->value('id');
    }
}

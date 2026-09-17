<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Collection;

class ListVendorCategoriesAction
{
    /**
     * Returns active root categories (with children) whose allowed_product_types
     * intersect with the vendor's approved product types.
     *
     * @return Collection<int, Category>
     */
    public function execute(VendorProfile $vendorProfile, ?ProductType $type = null): Collection
    {
        $approvedTypes = $vendorProfile->approvedTypes
            ->pluck('product_type')
            ->map(fn ($t) => $t->value)
            ->toArray();
        if ($type !== null) {
            $approvedTypes = in_array($type->value, $approvedTypes, true)
                ? [$type->value]
                : [];
        }

        $matchesApprovedType = function ($query) use ($approvedTypes): void {
            if ($approvedTypes === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function ($query) use ($approvedTypes): void {
                foreach ($approvedTypes as $type) {
                    $query->orWhereJsonContains('allowed_product_types', $type);
                }
            });
        };

        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->where($matchesApprovedType)
            ->with(['children' => fn ($query) => $query
                ->where('is_active', true)
                ->where($matchesApprovedType)
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }
}

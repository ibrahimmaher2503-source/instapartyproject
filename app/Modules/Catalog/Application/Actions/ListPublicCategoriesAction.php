<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListPublicCategoriesAction
{
    /**
     * Active categories ordered for display, optionally narrowed to those that
     * allow a given product type.
     *
     * @return Collection<int, Category>
     */
    public function execute(?ProductType $type = null): Collection
    {
        return Category::query()
            ->active()
            ->when(
                ! config('catalog.digital_services_enabled', true),
                fn (Builder $query) => $query->where(function (Builder $types): void {
                    $types->whereJsonContains('allowed_product_types', ProductType::Rental->value)
                        ->orWhereJsonContains('allowed_product_types', ProductType::Sale->value);
                }),
            )
            ->when($type !== null, fn (Builder $query) => $query->forProductType($type))
            ->orderBy('sort_order')
            ->get();
    }
}

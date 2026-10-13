<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListPublicOccasionsAction
{
    /**
     * Active occasions ordered for display.
     *
     * @return Collection<int, Occasion>
     */
    public function execute(?string $servicePublicId = null): Collection
    {
        $query = Occasion::query()->where('is_active', true);

        if ($servicePublicId !== null) {
            $categoryId = Service::query()
                ->displayable()
                ->where('public_id', $servicePublicId)
                ->value('category_id');

            if ($categoryId === null) {
                return $query->whereKey(-1)->get();
            }

            $query->whereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryId));
        }

        return $query->orderBy('sort_order')->get();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Repositories;

use App\Modules\Support\Domain\Models\FaqCategory;
use App\Modules\Support\Domain\Models\FaqItem;
use Illuminate\Database\Eloquent\Collection;

class EloquentFaqRepository
{
    /** @return Collection<int, FaqCategory> */
    public function getAllActiveCategories(): Collection
    {
        return FaqCategory::active()
            ->ordered()
            ->with(['items' => fn ($q) => $q->active()->ordered()])
            ->get();
    }

    /** @return Collection<int, FaqItem> */
    public function search(string $query): Collection
    {
        return FaqItem::search($query)
            ->query(fn ($q) => $q->where('is_active', true)->with('category'))
            ->get();
    }
}

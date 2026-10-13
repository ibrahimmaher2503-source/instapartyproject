<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Listeners;

use App\Modules\Catalog\Domain\Events\OccasionCategoriesChanged;
use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ReindexServicesForOccasionCategoriesChanged implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(OccasionCategoriesChanged $event): void
    {
        $categoryIds = array_values(array_unique(array_map('intval', $event->categoryIds)));

        if ($categoryIds === []) {
            return;
        }

        Service::query()
            ->published()
            ->whereIn('category_id', $categoryIds)
            ->searchable();
    }
}

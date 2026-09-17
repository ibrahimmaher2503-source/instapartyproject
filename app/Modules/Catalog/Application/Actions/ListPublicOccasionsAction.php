<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Occasion;
use Illuminate\Database\Eloquent\Collection;

class ListPublicOccasionsAction
{
    /**
     * Active occasions ordered for display.
     *
     * @return Collection<int, Occasion>
     */
    public function execute(): Collection
    {
        return Occasion::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}

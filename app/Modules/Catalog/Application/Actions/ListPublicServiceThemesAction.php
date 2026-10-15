<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ListPublicServiceThemesAction
{
    /**
     * @return Collection<int, ServiceTheme>
     */
    public function execute(string $servicePublicId): Collection
    {
        $service = Service::query()
            ->displayable()
            ->where('public_id', $servicePublicId)
            ->with(['themes' => fn (BelongsToMany $themes) => $themes->where('is_active', true)])
            ->first();

        return $service?->themes ?? new Collection;
    }
}

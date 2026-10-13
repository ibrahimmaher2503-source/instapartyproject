<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Application\DTOs\OccasionDTO;
use App\Modules\Catalog\Domain\Events\OccasionCategoriesChanged;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOccasionAction
{
    use RecordsTaxonomyAudit;

    public function execute(OccasionDTO $dto, ?User $actor = null): Occasion
    {
        return DB::transaction(function () use ($dto, $actor): Occasion {
            $occasion = Occasion::create([
                'public_id' => (string) Str::ulid(),
                ...$dto->toAttributes(),
            ]);

            $categoryIds = $dto->categoryIds ?? [];

            if ($categoryIds !== []) {
                $occasion->categories()->attach(array_fill_keys($categoryIds, ['sort_order' => 0]));
                DB::afterCommit(fn () => event(new OccasionCategoriesChanged($categoryIds)));
            }

            $changes = $dto->toAttributes();

            if ($dto->categoryIds !== null) {
                $changes['category_ids'] = $categoryIds;
            }

            $this->recordAudit($occasion, 'occasion_created', $actor, $changes);

            return $occasion;
        });
    }
}

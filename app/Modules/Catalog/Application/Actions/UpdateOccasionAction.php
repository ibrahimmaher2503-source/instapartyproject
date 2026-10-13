<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Concerns\RecordsTaxonomyAudit;
use App\Modules\Catalog\Application\DTOs\OccasionDTO;
use App\Modules\Catalog\Domain\Events\OccasionCategoriesChanged;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateOccasionAction
{
    use RecordsTaxonomyAudit;

    public function execute(Occasion $occasion, OccasionDTO $dto, ?User $actor = null): Occasion
    {
        return DB::transaction(function () use ($occasion, $dto, $actor): Occasion {
            $occasion = Occasion::query()->lockForUpdate()->findOrFail($occasion->getKey());
            $occasion->update($dto->toAttributes());

            $changes = $dto->toAttributes();

            if ($dto->categoryIds !== null) {
                $currentIds = DB::table('occasion_category')
                    ->where('occasion_id', $occasion->getKey())
                    ->pluck('category_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();
                $requestedIds = $dto->categoryIds;
                $removedIds = array_values(array_diff($currentIds, $requestedIds));
                $addedIds = array_values(array_diff($requestedIds, $currentIds));

                if ($removedIds !== []) {
                    DB::table('occasion_category')
                        ->where('occasion_id', $occasion->getKey())
                        ->whereIn('category_id', $removedIds)
                        ->delete();
                }

                if ($addedIds !== []) {
                    $occasion->categories()->attach(array_fill_keys($addedIds, ['sort_order' => 0]));
                }

                $affectedCategoryIds = array_values(array_unique([...$removedIds, ...$addedIds]));

                if ($affectedCategoryIds !== []) {
                    DB::afterCommit(fn () => event(new OccasionCategoriesChanged($affectedCategoryIds)));
                }

                $changes['category_ids'] = $requestedIds;
            }

            $this->recordAudit($occasion, 'occasion_updated', $actor, $changes);

            return $occasion->refresh();
        });
    }
}

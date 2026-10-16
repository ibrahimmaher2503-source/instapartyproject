<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\OccasionResource\Pages;

use App\Modules\Catalog\Application\Actions\DeleteOccasionAction;
use App\Modules\Catalog\Application\Actions\RestoreOccasionAction;
use App\Modules\Catalog\Application\Actions\UpdateOccasionAction;
use App\Modules\Catalog\Application\DTOs\OccasionDTO;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditOccasion extends EditRecord
{
    protected static string $resource = OccasionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->authorize('delete')
                ->modalDescription(__('catalog.occasion_delete_warning'))
                ->visible(fn (Occasion $record): bool => ! $record->trashed())
                ->using(function (Occasion $record): bool {
                    app(DeleteOccasionAction::class)->execute($record, auth()->user());

                    return true;
                }),
            RestoreAction::make()
                ->authorize('restore')
                ->visible(fn (Occasion $record): bool => $record->trashed())
                ->using(function (Occasion $record): bool {
                    app(RestoreOccasionAction::class)->execute($record, auth()->user());

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateOccasionAction::class)->execute(
            $record,
            OccasionDTO::fromArray($data),
            auth()->user(),
        );
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['category_ids'] = DB::table('occasion_category')
            ->where('occasion_id', $this->record->getKey())
            ->pluck('category_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return $data;
    }
}

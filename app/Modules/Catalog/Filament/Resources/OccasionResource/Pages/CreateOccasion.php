<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\OccasionResource\Pages;

use App\Modules\Catalog\Application\Actions\CreateOccasionAction;
use App\Modules\Catalog\Application\DTOs\OccasionDTO;
use App\Modules\Catalog\Filament\Resources\OccasionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOccasion extends CreateRecord
{
    protected static string $resource = OccasionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateOccasionAction::class)->execute(
            OccasionDTO::fromArray($data),
            auth()->user(),
        );
    }
}

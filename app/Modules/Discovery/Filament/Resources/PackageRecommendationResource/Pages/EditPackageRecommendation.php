<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages;

use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPackageRecommendation extends EditRecord
{
    protected static string $resource = PackageRecommendationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PackageRecommendation $record */
        return PackageRecommendationResource::persistViaAction($data, $record);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

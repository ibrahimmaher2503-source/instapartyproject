<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages;

use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePackageRecommendation extends CreateRecord
{
    protected static string $resource = PackageRecommendationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return PackageRecommendationResource::persistViaAction($data, null);
    }
}

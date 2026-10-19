<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource\Pages;

use App\Modules\Advertising\Application\Actions\CreateAdvertisementPackageAction;
use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAdvertisementPackage extends CreateRecord
{
    protected static string $resource = AdvertisementPackageResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateAdvertisementPackageAction::class)->execute($data);
    }
}

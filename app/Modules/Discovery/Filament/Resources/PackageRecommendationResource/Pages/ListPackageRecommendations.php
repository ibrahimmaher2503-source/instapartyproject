<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources\PackageRecommendationResource\Pages;

use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPackageRecommendations extends ListRecords
{
    protected static string $resource = PackageRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

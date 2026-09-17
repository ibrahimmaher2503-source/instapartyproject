<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages;

use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyPrograms extends ListRecords
{
    protected static string $resource = LoyaltyProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages;

use App\Modules\Settlement\Filament\Resources\CommissionRulesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommissionRule extends EditRecord
{
    protected static string $resource = CommissionRulesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

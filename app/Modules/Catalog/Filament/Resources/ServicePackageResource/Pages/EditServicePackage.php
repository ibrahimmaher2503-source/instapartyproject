<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ServicePackageResource\Pages;

use App\Modules\Catalog\Filament\Resources\ServicePackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServicePackage extends EditRecord
{
    protected static string $resource = ServicePackageResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

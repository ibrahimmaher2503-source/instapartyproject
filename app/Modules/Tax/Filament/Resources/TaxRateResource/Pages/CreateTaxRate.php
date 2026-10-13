<?php

declare(strict_types=1);

namespace App\Modules\Tax\Filament\Resources\TaxRateResource\Pages;

use App\Modules\Tax\Application\Actions\CreateTaxRateAction;
use App\Modules\Tax\Filament\Resources\TaxRateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTaxRate extends CreateRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateTaxRateAction::class)->execute($data);
    }
}

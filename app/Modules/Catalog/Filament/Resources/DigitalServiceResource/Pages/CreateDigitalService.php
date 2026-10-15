<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Catalog\Filament\Resources\DigitalServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateDigitalService extends CreateRecord
{
    protected static string $resource = DigitalServiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['product_type'] = ProductType::Digital;
        $data['status'] = DraftState::class;
        $nameEn = is_array($data['name']) ? $data['name']['en'] : $data['name'];
        $data['slug'] = Str::slug($nameEn).'-'.Str::lower(Str::random(6));

        return $data;
    }
}

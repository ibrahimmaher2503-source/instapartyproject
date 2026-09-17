<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateRentalService extends CreateRecord
{
    protected static string $resource = RentalServiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['product_type'] = ProductType::Rental;
        $nameEn = is_array($data['name']) ? $data['name']['en'] : $data['name'];
        $data['slug'] = Str::slug($nameEn).'-'.Str::lower(Str::random(6));

        return $data;
    }
}

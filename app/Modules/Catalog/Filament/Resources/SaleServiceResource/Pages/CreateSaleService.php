<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateSaleService extends CreateRecord
{
    protected static string $resource = SaleServiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['product_type'] = ProductType::Sale->value;
        $data['status'] = DraftState::class;
        $nameEn = is_array($data['name']) ? $data['name']['en'] : $data['name'];
        $data['slug'] = Str::slug($nameEn).'-'.Str::lower(Str::random(6));

        return $data;
    }
}

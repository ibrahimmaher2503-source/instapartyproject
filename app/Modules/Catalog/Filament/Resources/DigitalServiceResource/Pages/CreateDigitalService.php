<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\DigitalServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDigitalService extends CreateRecord
{
    protected static string $resource = DigitalServiceResource::class;
}

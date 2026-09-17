<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivitylogResource\Pages;

use App\Filament\Resources\ActivitylogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivitylog extends ListRecords
{
    protected static string $resource = ActivitylogResource::class;
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminActivityLogResource\Pages;

use App\Filament\Resources\AdminActivityLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminActivityLog extends ViewRecord
{
    protected static string $resource = AdminActivityLogResource::class;

    public static function getResource(): string
    {
        return AdminActivityLogResource::class;
    }
}

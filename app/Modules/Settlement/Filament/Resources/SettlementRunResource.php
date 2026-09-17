<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Domain\Models\SettlementRun;
use App\Modules\Settlement\Filament\Resources\SettlementRunResource\Pages\ListSettlementRuns;
use Filament\Resources\Resource;

class SettlementRunResource extends Resource
{
    protected static ?string $model = SettlementRun::class;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListSettlementRuns::route('/')];
    }
}

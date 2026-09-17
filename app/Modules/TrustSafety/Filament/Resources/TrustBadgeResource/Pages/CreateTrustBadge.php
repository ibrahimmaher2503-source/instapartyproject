<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages;

use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateTrustBadge extends CreateRecord
{
    use Translatable;

    protected static string $resource = TrustBadgeResource::class;
}

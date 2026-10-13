<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages;

use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrustBadge extends CreateRecord
{
    protected static string $resource = TrustBadgeResource::class;
}

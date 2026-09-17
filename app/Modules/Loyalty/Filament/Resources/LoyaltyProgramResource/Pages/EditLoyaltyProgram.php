<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages;

use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyProgram extends EditRecord
{
    protected static string $resource = LoyaltyProgramResource::class;
}

<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages;

use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoyaltyProgram extends CreateRecord
{
    protected static string $resource = LoyaltyProgramResource::class;
}

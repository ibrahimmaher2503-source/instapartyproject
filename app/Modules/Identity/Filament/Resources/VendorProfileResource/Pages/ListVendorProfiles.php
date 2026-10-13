<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages;

use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListVendorProfiles extends ListRecords
{
    protected static string $resource = VendorProfileResource::class;

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        return $query === null ? null : app(VendorApprovalEligibilityService::class)->withEligibilityRelations($query);
    }
}

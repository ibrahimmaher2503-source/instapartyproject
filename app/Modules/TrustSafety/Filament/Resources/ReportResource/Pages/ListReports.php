<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages;

use App\Modules\TrustSafety\Filament\Resources\ReportResource;
use Filament\Resources\Pages\ListRecords;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;
}

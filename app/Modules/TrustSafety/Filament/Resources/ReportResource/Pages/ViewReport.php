<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages;

use App\Modules\TrustSafety\Filament\Resources\ReportResource;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    public function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            Section::make('Report Details')
                ->schema([
                    TextEntry::make('public_id')->label('ID')->copyable(),
                    TextEntry::make('reporter.name')->label('Reporter'),
                    TextEntry::make('reportable_type')->label('Target Type')->badge(),
                    TextEntry::make('reportable_id')->label('Target ID'),
                    TextEntry::make('reason')->label('Reason')->badge(),
                    TextEntry::make('details')->label('Details'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('reviewed_by')->label('Reviewed By'),
                    TextEntry::make('reviewed_at')->label('Reviewed At')->dateTime(),
                    TextEntry::make('created_at')->label('Submitted At')->dateTime(),
                ])
                ->columns(2),
        ]);
    }
}

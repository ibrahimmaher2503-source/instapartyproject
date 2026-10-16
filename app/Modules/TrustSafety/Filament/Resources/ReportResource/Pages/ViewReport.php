<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages;

use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
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
            Section::make(__('trust-safety::trust-safety.fields.report_details'))
                ->schema([
                    TextEntry::make('public_id')->label(__('trust-safety::trust-safety.fields.id'))->copyable(),
                    TextEntry::make('reporter.name')->label(__('trust-safety::trust-safety.fields.reporter')),
                    TextEntry::make('reportable_type')
                        ->label(__('trust-safety::trust-safety.fields.target_type'))
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => ReportResource::formatReportableType($state)),
                    TextEntry::make('reportable_id')->label(__('trust-safety::trust-safety.fields.target_id')),
                    TextEntry::make('reason')
                        ->label(__('trust-safety::trust-safety.fields.reason'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state instanceof ReportReason ? $state->label() : ($state ? (ReportReason::tryFrom((string) $state)?->label() ?? (string) $state) : '')),
                    TextEntry::make('details')->label(__('trust-safety::trust-safety.fields.details')),
                    TextEntry::make('status')
                        ->label(__('trust-safety::trust-safety.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state instanceof ReportStatus ? $state->label() : ($state ? (ReportStatus::tryFrom((string) $state)?->label() ?? (string) $state) : '')),
                    TextEntry::make('reviewed_by')->label(__('trust-safety::trust-safety.fields.reviewed_by')),
                    TextEntry::make('reviewed_at')->label(__('trust-safety::trust-safety.fields.reviewed_at'))->dateTime(),
                    TextEntry::make('created_at')->label(__('trust-safety::trust-safety.fields.submitted_at'))->dateTime(),
                ])
                ->columns(2),
        ]);
    }
}

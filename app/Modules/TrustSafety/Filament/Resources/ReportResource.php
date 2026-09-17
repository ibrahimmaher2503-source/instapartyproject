<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources;

use App\Modules\TrustSafety\Application\Actions\DismissReportAction;
use App\Modules\TrustSafety\Application\Actions\MarkReportUnderReviewAction;
use App\Modules\TrustSafety\Application\Actions\ResolveReportAction;
use App\Modules\TrustSafety\Domain\Enums\ReportReason;
use App\Modules\TrustSafety\Domain\Enums\ReportStatus;
use App\Modules\TrustSafety\Domain\Models\Report;
use App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages\ListReports;
use App\Modules\TrustSafety\Filament\Resources\ReportResource\Pages\ViewReport;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.trust_safety');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->searchable(),

                TextColumn::make('reportable_type')
                    ->badge()
                    ->label('Target Type'),

                TextColumn::make('reason')
                    ->badge()
                    ->color(fn (ReportReason $state): string => match ($state) {
                        ReportReason::FraudulentActivity => 'danger',
                        ReportReason::Harassment => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (ReportReason $state) => $state->label())
                    ->label('Reason'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ReportStatus $state): string => match ($state) {
                        ReportStatus::Open => 'warning',
                        ReportStatus::UnderReview => 'info',
                        ReportStatus::Resolved => 'success',
                        ReportStatus::Dismissed => 'gray',
                    })
                    ->formatStateUsing(fn (ReportStatus $state) => $state->label())
                    ->label('Status'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Submitted'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ReportStatus::cases())->mapWithKeys(
                        fn (ReportStatus $s) => [$s->value => $s->label()]
                    ))
                    ->label('Status'),

                SelectFilter::make('reason')
                    ->options(collect(ReportReason::cases())->mapWithKeys(
                        fn (ReportReason $r) => [$r->value => $r->label()]
                    ))
                    ->label('Reason'),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('markUnderReview')
                    ->label('Mark Under Review')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open)
                    ->action(function (Report $record): void {
                        app(MarkReportUnderReviewAction::class)->execute($record);
                        Notification::make()->title('Report marked under review')->info()->send();
                    }),

                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status === ReportStatus::UnderReview)
                    ->action(function (Report $record): void {
                        app(ResolveReportAction::class)->execute($record);
                        Notification::make()->title('Report resolved')->success()->send();
                    }),

                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status === ReportStatus::UnderReview)
                    ->action(function (Report $record): void {
                        app(DismissReportAction::class)->execute($record);
                        Notification::make()->title('Report dismissed')->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
            'view' => ViewReport::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

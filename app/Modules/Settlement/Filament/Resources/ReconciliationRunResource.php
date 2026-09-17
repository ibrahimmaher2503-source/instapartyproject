<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use App\Modules\Settlement\Filament\Resources\ReconciliationRunResource\Pages\ListReconciliationRuns;
use App\Modules\Settlement\Filament\Resources\ReconciliationRunResource\Pages\ViewReconciliationRun;
use App\Modules\Settlement\Filament\Resources\ReconciliationRunResource\RelationManagers\FindingsRelationManager;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReconciliationRunResource extends Resource
{
    protected static ?string $model = ReconciliationRun::class;

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.reconciliation_runs');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.reconciliation_run.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.reconciliation_run.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('settlement.columns.public_id'))
                    ->searchable()
                    ->copyable(),

                TextColumn::make('scope_type')
                    ->badge()
                    ->label(__('settlement.columns.scope_type')),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ReconciliationStatus $state): string => match ($state) {
                        ReconciliationStatus::Clean => 'success',
                        ReconciliationStatus::Repaired => 'warning',
                        ReconciliationStatus::RequiresManualReview => 'danger',
                        ReconciliationStatus::Running => 'info',
                        ReconciliationStatus::Failed => 'danger',
                        default => 'gray',
                    })
                    ->label(__('settlement.columns.status')),

                TextColumn::make('wallets_scanned')
                    ->label(__('settlement.columns.wallets_scanned'))
                    ->numeric(),

                TextColumn::make('findings_count')
                    ->label(__('settlement.columns.findings_count'))
                    ->numeric(),

                TextColumn::make('manual_review_count')
                    ->label(__('settlement.columns.manual_review_count'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->numeric(),

                TextColumn::make('started_at')
                    ->dateTime()
                    ->label(__('settlement.columns.started_at'))
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->dateTime()
                    ->label(__('settlement.columns.completed_at'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ReconciliationStatus::class)
                    ->label(__('settlement.columns.status')),

                SelectFilter::make('scope_type')
                    ->options([
                        'all' => __('settlement.scope_type_options.all'),
                        'wallet' => __('settlement.scope_type_options.wallet'),
                        'vendor' => __('settlement.scope_type_options.vendor'),
                        'date_range' => __('settlement.scope_type_options.date_range'),
                        'recent_touch' => __('settlement.scope_type_options.recent_touch'),
                    ])
                    ->label(__('settlement.columns.scope_type')),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            Section::make(__('settlement.reconciliation_run.summary'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('public_id')
                            ->label(__('settlement.columns.public_id'))
                            ->copyable(),

                        TextEntry::make('scope_type')
                            ->badge()
                            ->label(__('settlement.columns.scope_type')),

                        TextEntry::make('status')
                            ->badge()
                            ->label(__('settlement.columns.status')),

                        TextEntry::make('wallets_scanned')
                            ->label(__('settlement.columns.wallets_scanned')),

                        TextEntry::make('findings_count')
                            ->label(__('settlement.columns.findings_count')),

                        TextEntry::make('auto_repaired_count')
                            ->label(__('settlement.columns.auto_repaired_count')),

                        TextEntry::make('manual_review_count')
                            ->label(__('settlement.columns.manual_review_count')),

                        TextEntry::make('started_at')
                            ->dateTime()
                            ->label(__('settlement.columns.started_at')),

                        TextEntry::make('completed_at')
                            ->dateTime()
                            ->label(__('settlement.columns.completed_at')),
                    ]),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            FindingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReconciliationRuns::route('/'),
            'view' => ViewReconciliationRun::route('/{record}'),
        ];
    }
}

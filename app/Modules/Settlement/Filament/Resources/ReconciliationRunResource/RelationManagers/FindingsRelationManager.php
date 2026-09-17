<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\ReconciliationRunResource\RelationManagers;

use App\Modules\Settlement\Application\Support\FinanceIdentityPresenter;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'findings';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('settlement.columns.public_id'))
                    ->searchable()
                    ->copyable(),

                TextColumn::make('finding_type')
                    ->badge()
                    ->color(fn (ReconciliationFindingType $state): string => match ($state) {
                        ReconciliationFindingType::WalletCacheDrift => 'warning',
                        ReconciliationFindingType::OrphanedRefundRow => 'danger',
                        ReconciliationFindingType::OrphanedLedgerEntry => 'danger',
                        ReconciliationFindingType::UnbalancedTransactionGroup => 'danger',
                        ReconciliationFindingType::CommissionWithoutSnapshotRate => 'danger',
                        ReconciliationFindingType::WithdrawalWithoutReserveEntry => 'danger',
                        ReconciliationFindingType::NegativeVendorBalance => 'danger',
                        ReconciliationFindingType::CurrencyMismatch => 'danger',
                    })
                    ->label(__('settlement.columns.finding_type')),

                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (ReconciliationFindingSeverity $state): string => match ($state) {
                        ReconciliationFindingSeverity::Info => 'info',
                        ReconciliationFindingSeverity::Warning => 'warning',
                        ReconciliationFindingSeverity::High => 'danger',
                    })
                    ->label(__('settlement.columns.severity')),

                TextColumn::make('resource_type')
                    ->label(__('settlement.columns.resource'))
                    ->state(fn (ReconciliationFinding $record): string => FinanceIdentityPresenter::reference(
                        $record->resource_type,
                        $record->resource_id,
                    )),

                TextColumn::make('resolution')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'auto_repaired' => 'success',
                        'ignored' => 'gray',
                        default => 'warning',
                    })
                    ->default(__('settlement.findings.unresolved'))
                    ->label(__('settlement.columns.resolution')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('settlement.columns.detected_at')),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->options(ReconciliationFindingSeverity::class)
                    ->label(__('settlement.columns.severity')),

                SelectFilter::make('finding_type')
                    ->options(ReconciliationFindingType::class)
                    ->label(__('settlement.columns.finding_type')),

                SelectFilter::make('resolution')
                    ->options([
                        'auto_repaired' => __('settlement.findings.auto_repaired'),
                        'ignored' => __('settlement.findings.ignored'),
                    ])
                    ->label(__('settlement.columns.resolution')),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }
}

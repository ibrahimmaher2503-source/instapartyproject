<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource\RelationManagers;

use App\Modules\Settlement\Application\Support\FinanceIdentityPresenter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentLedgerRepository;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('settlement.ledger_entries_title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (LedgerDirection $state): string => match ($state) {
                        LedgerDirection::Credit => 'success',
                        LedgerDirection::Debit => 'danger',
                    })
                    ->label(__('settlement.columns.direction')),

                TextColumn::make('entry_type')
                    ->badge()
                    ->label(__('settlement.columns.entry_type')),

                TextColumn::make('amount_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.columns.amount'))
                    ->sortable(),

                TextColumn::make('running_balance_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.columns.running_balance'))
                    ->sortable(),

                TextColumn::make('transaction_group_id')
                    ->label(__('settlement.columns.group_id'))
                    ->state(fn (Model $record): string => FinanceIdentityPresenter::reference(
                        'ledger_transaction_groups',
                        $record->transaction_group_id,
                    )),

                TextColumn::make('correlation_id')
                    ->label(__('settlement.columns.correlation_id'))
                    ->copyable()
                    ->limit(26)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('causation_id')
                    ->label(__('settlement.columns.causation_id'))
                    ->copyable()
                    ->limit(26)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('related_entity_type')
                    ->label(__('settlement.columns.related_entity'))
                    ->state(fn (Model $record): string => FinanceIdentityPresenter::reference(
                        $record->related_entity_type,
                        $record->related_entity_id,
                    )),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('settlement.created_at'))
                    ->sortable(),
            ])
            ->actions([
                Action::make('causalChain')
                    ->label(__('settlement.actions.show_causal_chain'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->modalHeading(__('settlement.actions.causal_chain_title'))
                    ->infolist(function ($record): array {
                        $chain = app(EloquentLedgerRepository::class)->causalChainFor($record->id);

                        return [
                            Section::make(__('settlement.actions.causal_chain_entries', ['count' => $chain->count()]))
                                ->schema(
                                    $chain->map(fn ($entry, $idx) => Grid::make(5)->schema([
                                        TextEntry::make("chain_{$idx}_direction")
                                            ->label(__('settlement.columns.direction'))
                                            ->state($entry->direction?->value ?? '—')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'credit' ? 'success' : 'danger'),

                                        TextEntry::make("chain_{$idx}_entry_type")
                                            ->label(__('settlement.columns.entry_type'))
                                            ->state($entry->entry_type?->value ?? '—'),

                                        TextEntry::make("chain_{$idx}_amount")
                                            ->label(__('settlement.columns.amount'))
                                            ->state(number_format($entry->amount_minor / 100, 2).' EGP'),

                                        TextEntry::make("chain_{$idx}_correlation")
                                            ->label(__('settlement.columns.correlation_id'))
                                            ->state($entry->correlation_id ?? '—')
                                            ->copyable(),

                                        TextEntry::make("chain_{$idx}_causation")
                                            ->label(__('settlement.columns.causation_id'))
                                            ->state($entry->causation_id ?? '—')
                                            ->copyable(),
                                    ]))->values()->all()
                                ),
                        ];
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('settlement.actions.close')),
            ])
            ->paginated([25, 50, 100]);
    }
}

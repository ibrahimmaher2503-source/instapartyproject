<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Application\Support\FinanceIdentityPresenter;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource\Pages\ListWalletLedger;
use App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource\Pages\ViewWalletLedger;
use App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource\RelationManagers\LedgerEntriesRelationManager;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletLedgerViewerResource extends Resource
{
    protected static ?string $model = Wallet::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static ?string $slug = 'settlement-wallets';

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.wallet_ledger');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.wallet.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.wallet.plural');
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

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            Section::make(__('settlement.wallet.section_details'))
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('public_id')
                                ->label(__('settlement.columns.reference'))
                                ->copyable(),

                            TextEntry::make('owner_id')
                                ->label(__('settlement.columns.owner'))
                                ->state(fn (Wallet $record): string => FinanceIdentityPresenter::reference(
                                    $record->owner_type,
                                    $record->owner_id,
                                )),

                            TextEntry::make('currency')
                                ->label(__('settlement.columns.currency')),

                            TextEntry::make('balance_minor')
                                ->label(__('settlement.columns.balance'))
                                ->money('EGP', divideBy: 100),

                            TextEntry::make('pending_withdrawal_minor')
                                ->label(__('settlement.columns.pending_withdrawal'))
                                ->money('EGP', divideBy: 100),

                            TextEntry::make('updated_at')
                                ->label(__('settlement.columns.last_updated'))
                                ->dateTime(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('settlement.columns.reference'))
                    ->searchable()
                    ->copyable(),

                TextColumn::make('owner_id')
                    ->label(__('settlement.columns.owner'))
                    ->state(fn (Wallet $record): string => FinanceIdentityPresenter::reference(
                        $record->owner_type,
                        $record->owner_id,
                    )),

                TextColumn::make('balance_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.columns.balance'))
                    ->sortable(),

                TextColumn::make('pending_withdrawal_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.columns.pending_withdrawal')),

                TextColumn::make('currency')
                    ->label(__('settlement.columns.currency')),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->label(__('settlement.columns.last_updated'))
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LedgerEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalletLedger::route('/'),
            'view' => ViewWalletLedger::route('/{record}'),
        ];
    }
}

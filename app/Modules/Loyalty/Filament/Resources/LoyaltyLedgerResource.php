<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Application\Actions\AdjustLoyaltyBalanceAction;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Filament\Resources\LoyaltyLedgerResource\Pages\ListLoyaltyLedger;
use DomainException;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyLedgerResource extends Resource
{
    protected static ?string $model = LoyaltyLedgerEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.loyalty');
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.ledger');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.ledger_entry.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.ledger_entry.plural');
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'vendorProfile']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('loyalty.columns.public_id'))
                    ->copyable()
                    ->searchable()
                    ->limit(10),
                TextColumn::make('user.name')
                    ->label(__('loyalty.columns.user'))
                    ->searchable(),
                TextColumn::make('vendorProfile.business_name')
                    ->label(__('loyalty.columns.vendor'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                TextColumn::make('direction')
                    ->label(__('loyalty.columns.direction'))
                    ->badge()
                    ->color(fn (LedgerDirection $state): string => match ($state) {
                        LedgerDirection::Earn => 'success',
                        LedgerDirection::Redeem => 'warning',
                        LedgerDirection::Expire => 'gray',
                        LedgerDirection::Adjust => 'info',
                    })
                    ->formatStateUsing(fn (LedgerDirection $state) => $state->label()),
                TextColumn::make('points')
                    ->label(__('loyalty.columns.points'))
                    ->formatStateUsing(function ($state, LoyaltyLedgerEntry $record): string {
                        $sign = match (true) {
                            $record->direction === LedgerDirection::Earn => '+',
                            $record->direction === LedgerDirection::Adjust && (int) $record->points > 0 => '+',
                            $record->direction === LedgerDirection::Redeem,
                            $record->direction === LedgerDirection::Expire => '−',
                            default => '−',
                        };

                        return $sign.(string) $state;
                    })
                    ->sortable(),
                TextColumn::make('balance_after')
                    ->label(__('loyalty.columns.balance_after'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reference_type')
                    ->label(__('loyalty.columns.reference_type'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('reference_id')
                    ->label(__('loyalty.columns.reference_id'))
                    ->toggleable(),
                TextColumn::make('reason')
                    ->label(__('loyalty.columns.reason'))
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state[app()->getLocale()] ?? $state['en'] ?? '')
                        : (string) ($state ?? ''))
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('expires_at')
                    ->label(__('loyalty.columns.expires_at'))
                    ->dateTime()
                    ->default(__('loyalty.no_expiry'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('direction')
                    ->label(__('loyalty.columns.direction'))
                    ->options(collect(LedgerDirection::cases())
                        ->mapWithKeys(fn (LedgerDirection $d) => [$d->value => $d->label()])
                        ->all()),
                SelectFilter::make('user_id')
                    ->label(__('loyalty.columns.user'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(false),
                SelectFilter::make('vendor_profile_id')
                    ->label(__('loyalty.columns.vendor'))
                    ->relationship('vendorProfile', 'business_name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => is_array($record->business_name)
                            ? ($record->business_name['en'] ?? $record->public_id)
                            : ($record->business_name ?? $record->public_id)
                    )
                    ->searchable()
                    ->preload(false),
            ])
            ->headerActions([
                Action::make('adjustBalance')
                    ->label(__('loyalty.actions.adjust_balance'))
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->form([
                        TextInput::make('user_id')
                            ->label(__('loyalty.columns.user'))
                            ->numeric()
                            ->required(),
                        TextInput::make('vendor_profile_id')
                            ->label(__('loyalty.columns.vendor'))
                            ->numeric()
                            ->required(),
                        TextInput::make('points_delta')
                            ->label(__('loyalty.fields.points_delta'))
                            ->numeric()
                            ->required()
                            ->helperText(__('loyalty.help.points_delta')),
                        TextInput::make('reason_en')
                            ->label(__('loyalty.fields.reason_en'))
                            ->required()
                            ->maxLength(500),
                        TextInput::make('reason_ar')
                            ->label(__('loyalty.fields.reason_ar'))
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (array $data): void {
                        try {
                            app(AdjustLoyaltyBalanceAction::class)->execute(
                                (int) $data['user_id'],
                                (int) $data['vendor_profile_id'],
                                (int) $data['points_delta'],
                                (string) $data['reason_en'],
                                (string) $data['reason_ar'],
                                (int) auth()->id(),
                            );

                            Notification::make()
                                ->title(__('loyalty.notifications.adjust_success'))
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()
                                ->title(__('loyalty.notifications.adjust_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyLedger::route('/'),
        ];
    }
}

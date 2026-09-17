<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Resources;

use App\Modules\Advertising\Application\Actions\CancelAdSubscriptionAction;
use App\Modules\Advertising\Domain\Enums\AdSubscriptionStatus;
use App\Modules\Advertising\Domain\Enums\PlacementType;
use App\Modules\Advertising\Domain\Models\VendorAdSubscription;
use App\Modules\Advertising\Filament\Resources\VendorAdSubscriptionResource\Pages\ListVendorAdSubscriptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VendorAdSubscriptionResource extends Resource
{
    protected static ?string $model = VendorAdSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-tv';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.advertising');
    }

    public static function getModelLabel(): string
    {
        return __('advertising.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising.subscriptions');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Select::make('vendor_profile_id')
                ->label(__('advertising.vendor'))
                ->relationship('vendor', 'business_name->en')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('advertisement_package_id')
                ->label(__('advertising.package'))
                ->relationship('package', 'name->en')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('status')
                ->label(__('advertising.status'))
                ->options(collect(AdSubscriptionStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('shared.common.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('vendor.business_name')
                    ->label(__('advertising.vendor'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('package.name')
                    ->label(__('advertising.package'))
                    ->searchable(),
                TextColumn::make('package.placement_type')
                    ->label(__('advertising.placement_type'))
                    ->badge()
                    ->color(fn (PlacementType $state): string => $state->color())
                    ->formatStateUsing(fn (PlacementType $state): string => $state->label()),
                TextColumn::make('status')
                    ->label(__('advertising.status'))
                    ->badge()
                    ->color(fn (AdSubscriptionStatus $state): string => $state->color())
                    ->formatStateUsing(fn (AdSubscriptionStatus $state): string => $state->label()),
                TextColumn::make('starts_at')
                    ->label(__('advertising.starts_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('advertising.ends_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('impression_count')
                    ->label(__('advertising.impressions'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('click_count')
                    ->label(__('advertising.clicks'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_minor')
                    ->label(__('advertising.total'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(AdSubscriptionStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->label(__('advertising.status')),
                SelectFilter::make('placement_type')
                    ->options(collect(PlacementType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->query(fn ($query, array $data) => filled($data['value'])
                        ? $query->whereHas('package', fn ($q) => $q->where('placement_type', $data['value']))
                        : $query)
                    ->label(__('advertising.placement_type')),
            ])
            ->actions([
                Action::make('cancel')
                    ->label(__('advertising.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VendorAdSubscription $record): bool => $record->status === AdSubscriptionStatus::Active)
                    ->action(function (VendorAdSubscription $record): void {
                        app(CancelAdSubscriptionAction::class)->execute($record, (int) auth()->id());
                        Notification::make()->title(__('advertising.cancelled_successfully'))->success()->send();
                    }),
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorAdSubscriptions::route('/'),
        ];
    }
}

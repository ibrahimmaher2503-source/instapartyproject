<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources;

use App\Modules\Subscriptions\Domain\Enums\PlanCode;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource\Pages\CreateSubscriptionPlan;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource\Pages\EditSubscriptionPlan;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource\Pages\ListSubscriptionPlans;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource\RelationManagers\PlanFeaturesRelationManager;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    use Translatable;

    protected static ?string $model = SubscriptionPlan::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.subscriptions');
    }

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('subscriptions::subscription.plans');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $schema): Form
    {
        return $schema
            ->components([
                Tabs::make('Translations')
                    ->tabs([
                        Tab::make('English')
                            ->schema(static::translatableFields('en')),
                        Tab::make('العربية')
                            ->schema(static::translatableFields('ar')),
                    ])
                    ->columnSpanFull(),

                Section::make(__('subscriptions::subscription.plan_details'))
                    ->schema([
                        Select::make('plan_code')
                            ->options(PlanCode::class)
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        TextInput::make('monthly_price_minor')
                            ->label(__('subscriptions::subscription.monthly_price'))
                            ->numeric()
                            ->required()
                            ->minValue(0),

                        TextInput::make('yearly_price_minor')
                            ->label(__('subscriptions::subscription.yearly_price'))
                            ->numeric()
                            ->required()
                            ->minValue(0),

                        TextInput::make('display_order')
                            ->label(__('subscriptions::subscription.display_order'))
                            ->numeric()
                            ->required()
                            ->minValue(0),

                        Toggle::make('is_published')
                            ->label(__('subscriptions::subscription.is_published'))
                            ->default(true),

                        Toggle::make('is_default')
                            ->label(__('subscriptions::subscription.is_default'))
                            ->helperText(__('subscriptions::subscription.is_default_hint')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan_code')
                    ->badge()
                    ->color(fn (PlanCode $state): string => match ($state) {
                        PlanCode::Free => 'gray',
                        PlanCode::Silver => 'info',
                        PlanCode::Gold => 'warning',
                        PlanCode::Premium => 'success',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label(__('subscriptions::subscription.plan_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('monthly_price_minor')
                    ->label(__('subscriptions::subscription.monthly_price'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                TextColumn::make('yearly_price_minor')
                    ->label(__('subscriptions::subscription.yearly_price'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label(__('subscriptions::subscription.display_order'))
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label(__('subscriptions::subscription.is_published'))
                    ->boolean(),

                IconColumn::make('is_default')
                    ->label(__('subscriptions::subscription.is_default'))
                    ->boolean(),

                TextColumn::make('deleted_at')
                    ->label(__('subscriptions::subscription.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('is_published')
                    ->label(__('subscriptions::subscription.is_published')),
                TernaryFilter::make('is_default')
                    ->label(__('subscriptions::subscription.is_default')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('display_order', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            PlanFeaturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPlans::route('/'),
            'create' => CreateSubscriptionPlan::route('/create'),
            'edit' => EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }

    protected static function translatableFields(?string $locale = null): array
    {
        return [
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->rows(3)
                ->maxLength(500),
        ];
    }
}

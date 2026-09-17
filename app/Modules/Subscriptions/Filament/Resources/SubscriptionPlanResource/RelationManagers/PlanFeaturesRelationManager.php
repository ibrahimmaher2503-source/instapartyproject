<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\SubscriptionPlanResource\RelationManagers;

use App\Modules\Subscriptions\Application\Actions\InvalidatePlanFeaturesCache;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlanFeaturesRelationManager extends RelationManager
{
    protected static string $relationship = 'features';

    public function form(Form $schema): Form
    {
        return $schema
            ->components([
                TextInput::make('feature_key')
                    ->required()
                    ->maxLength(60)
                    ->unique(ignoreRecord: true),

                Select::make('value_type')
                    ->options(__('subscriptions::subscription.feature.value_types'))
                    ->required()
                    ->live(),

                TextInput::make('value_int')
                    ->label(__('subscriptions::subscription.feature.value_int'))
                    ->numeric()
                    ->visible(fn ($get) => $get('value_type') === 'int'),

                Toggle::make('value_bool')
                    ->label(__('subscriptions::subscription.feature.value_bool'))
                    ->visible(fn ($get) => $get('value_type') === 'bool'),

                TextInput::make('value_string')
                    ->label(__('subscriptions::subscription.feature.value_string'))
                    ->maxLength(255)
                    ->visible(fn ($get) => $get('value_type') === 'string'),

                TextInput::make('label')
                    ->label(__('subscriptions::subscription.feature.label_en'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('feature_key')
            ->columns([
                TextColumn::make('feature_key')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('value_type')
                    ->badge(),

                TextColumn::make('value_int')
                    ->label(__('subscriptions::subscription.feature.value'))
                    ->formatStateUsing(fn ($record) => match ($record->value_type) {
                        'int' => $record->value_int,
                        'bool' => $record->value_bool ? __('shared::shared.common.yes') : __('shared::shared.common.no'),
                        'string' => $record->value_string,
                        default => '—',
                    })
                    ->sortable(),

                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function handleRecordUpdate(Model $record, array $data): Model
    {
        $record = parent::handleRecordUpdate($record, $data);
        app(InvalidatePlanFeaturesCache::class)->execute($this->getOwnerRecord()->id);

        return $record;
    }

    public function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);
        app(InvalidatePlanFeaturesCache::class)->execute($this->getOwnerRecord()->id);

        return $record;
    }

    public function handleRecordDelete(Model $record): void
    {
        parent::handleRecordDelete($record);
        app(InvalidatePlanFeaturesCache::class)->execute($this->getOwnerRecord()->id);
    }
}

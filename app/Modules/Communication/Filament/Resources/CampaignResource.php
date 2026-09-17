<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Application\Actions\CampaignAlreadyDispatchedException;
use App\Modules\Communication\Application\Actions\CampaignCannotBeCancelledException;
use App\Modules\Communication\Application\Actions\CancelCampaignAction;
use App\Modules\Communication\Application\Actions\DispatchCampaignAction;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use App\Modules\Communication\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use App\Modules\Communication\Filament\Resources\CampaignResource\Pages\ViewCampaign;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.communication');
    }

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('communication.nav.campaigns');
    }

    public static function getModelLabel(): string
    {
        return __('communication.models.campaign.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('communication.models.campaign.plural');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('communication.campaign.form.name'))
                ->required()
                ->maxLength(160)
                ->columnSpanFull(),

            Select::make('channel')
                ->label(__('communication.campaign.form.channel'))
                ->options(collect(CampaignChannel::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                ->required(),

            Select::make('target_locale')
                ->label(__('communication.campaign.form.target_locale'))
                ->options(collect(CampaignTargetLocale::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                ->required(),

            KeyValue::make('segment_filters')
                ->label(__('communication.campaign.form.segment_filters'))
                ->helperText(__('communication.campaign.form.segment_filters_hint'))
                ->keyLabel(__('communication.campaign.form.filter_key'))
                ->valueLabel(__('communication.campaign.form.filter_value'))
                ->required()
                ->columnSpanFull(),

            Tabs::make(__('communication.campaign.form.content_tabs'))
                ->tabs([
                    Tab::make(__('communication.campaign.form.tab_english'))
                        ->schema([
                            TextInput::make('subject.en')
                                ->label(__('communication.campaign.form.subject_en'))
                                ->maxLength(255),
                            Textarea::make('body.en')
                                ->label(__('communication.campaign.form.body_en'))
                                ->required()
                                ->rows(4)
                                ->helperText(__('communication.campaign.form.body_en_hint')),
                        ]),
                    Tab::make(__('communication.campaign.form.tab_arabic'))
                        ->schema([
                            TextInput::make('subject.ar')
                                ->label(__('communication.campaign.form.subject_ar'))
                                ->maxLength(255),
                            Textarea::make('body.ar')
                                ->label(__('communication.campaign.form.body_ar'))
                                ->required()
                                ->rows(4),
                        ]),
                ])
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (CampaignChannel $state) => $state->label()),
                TextColumn::make('target_locale')
                    ->badge()
                    ->formatStateUsing(fn (CampaignTargetLocale $state) => $state->label()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (CampaignStatus $state) => $state->color())
                    ->formatStateUsing(fn (CampaignStatus $state) => $state->label()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(CampaignStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                SelectFilter::make('channel')
                    ->options(collect(CampaignChannel::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
            ])
            ->actions([
                Action::make('sendNow')
                    ->label(__('communication.campaign.actions.send_now'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('communication.campaign.dispatch_heading'))
                    ->modalDescription(__('communication.campaign.dispatch_description'))
                    ->visible(fn (Campaign $record) => $record->isDraft())
                    ->action(function (Campaign $record) {
                        try {
                            app(DispatchCampaignAction::class)->execute($record);
                            Notification::make()
                                ->title(__('communication.campaign.dispatched'))
                                ->body(__('communication.campaign.dispatched_body'))
                                ->success()
                                ->send();
                        } catch (CampaignAlreadyDispatchedException $e) {
                            Notification::make()
                                ->title(__('communication.campaign.already_dispatched'))
                                ->danger()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title(__('communication.campaign.dispatch_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label(__('communication.campaign.actions.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Campaign $record) => in_array($record->status, [CampaignStatus::Draft, CampaignStatus::Scheduled, CampaignStatus::Running], true))
                    ->action(function (Campaign $record) {
                        try {
                            app(CancelCampaignAction::class)->execute($record);
                            Notification::make()
                                ->title(__('communication.campaign.cancelled'))
                                ->success()
                                ->send();
                        } catch (CampaignCannotBeCancelledException $e) {
                            Notification::make()
                                ->title(__('communication.campaign.cannot_cancel'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(fn (Campaign $record) => $record->isDraft()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'view' => ViewCampaign::route('/{record}'),
        ];
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}

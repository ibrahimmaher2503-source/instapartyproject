<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Application\Services\ResolvedTemplate;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages\CreateNotificationTemplate;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages\EditNotificationTemplate;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages\ListNotificationTemplates;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotificationTemplateResource extends Resource
{
    use Translatable;

    protected static ?string $model = NotificationTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.communication');
    }

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('communication.nav.notification_templates');
    }

    public static function getModelLabel(): string
    {
        return __('communication.models.notification_template.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('communication.models.notification_template.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make(__('communication.resource.notification_templates'))
                ->schema([
                    TextInput::make('event_key')
                        ->label(__('communication.resource.event_key'))
                        ->required()
                        ->maxLength(100)
                        ->columnSpan(1),

                    Select::make('channel')
                        ->label(__('communication.resource.channel'))
                        ->options(collect(NotificationChannel::cases())->mapWithKeys(
                            fn (NotificationChannel $c) => [$c->value => $c->label()]
                        ))
                        ->required()
                        ->columnSpan(1),

                    Select::make('audience')
                        ->label(__('communication.resource.audience'))
                        ->options(collect(NotificationAudience::cases())->mapWithKeys(
                            fn (NotificationAudience $a) => [$a->value => $a->label()]
                        ))
                        ->required()
                        ->columnSpan(1),

                    Toggle::make('is_active')
                        ->label(__('communication.resource.is_active'))
                        ->default(true)
                        ->columnSpan(1),
                ])
                ->columns(2),

            Section::make(__('communication.template.section_content'))
                ->schema([
                    Textarea::make('subject')
                        ->label(__('communication.resource.subject'))
                        ->rows(2)
                        ->live(onBlur: true)
                        ->nullable()
                        ->columnSpanFull(),

                    Textarea::make('body')
                        ->label(__('communication.resource.body'))
                        ->rows(5)
                        ->live(onBlur: true)
                        ->required()
                        ->columnSpanFull(),

                    KeyValue::make('variables')
                        ->label(__('communication.resource.variables'))
                        ->keyLabel(__('communication.template.variable_key'))
                        ->valueLabel(__('communication.template.variable_description'))
                        ->nullable()
                        ->live(onBlur: true)
                        ->columnSpanFull(),

                    Placeholder::make('preview')
                        ->label(__('communication.template.preview'))
                        ->content(function (Get $get): string {
                            $body = $get('body');
                            $body = is_array($body) ? ($body[app()->getLocale()] ?? reset($body) ?: '') : (string) $body;

                            return $body === '' ? __('communication.template.preview_empty') : $body;
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function validateFormData(array $data): array
    {
        if (empty(trim((string) ($data['body']['ar'] ?? '')))) {
            throw ValidationException::withMessages(['body' => [__('communication.validation.body_ar_required')]]);
        }

        $templates = array_merge(array_values((array) ($data['body'] ?? [])), array_values((array) ($data['subject'] ?? [])));
        $used = ResolvedTemplate::variableNames(...array_map('strval', $templates));
        $declared = array_keys((array) ($data['variables'] ?? []));
        $missing = array_values(array_diff($used, $declared));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'variables' => [__('communication.validation.variables_undocumented', ['variables' => implode(', ', $missing)])],
            ]);
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_key')
                    ->label(__('communication.resource.event_key'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '';
                        }
                        $key = 'communication.event_keys.'.str_replace('.', '_', $state);
                        $translated = __($key);

                        return $translated === $key ? Str::headline(str_replace('.', ' ', $state)) : $translated;
                    })
                    ->description(fn (NotificationTemplate $record): string => $record->event_key)
                    ->wrap(),

                TextColumn::make('channel')
                    ->label(__('communication.resource.channel'))
                    ->badge()
                    ->color(fn (NotificationChannel $state): string => match ($state) {
                        NotificationChannel::Push => 'info',
                        NotificationChannel::Sms => 'warning',
                        NotificationChannel::Whatsapp => 'success',
                        NotificationChannel::Email => 'primary',
                        NotificationChannel::InApp => 'gray',
                    })
                    ->formatStateUsing(fn (NotificationChannel $state) => $state->label()),

                TextColumn::make('audience')
                    ->label(__('communication.resource.audience'))
                    ->badge()
                    ->color(fn (NotificationAudience $state): string => match ($state) {
                        NotificationAudience::Customer => 'success',
                        NotificationAudience::Vendor => 'warning',
                        NotificationAudience::Admin => 'danger',
                    })
                    ->formatStateUsing(fn (NotificationAudience $state) => $state->label()),

                IconColumn::make('is_active')
                    ->label(__('communication.resource.is_active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('event_key')
            ->filters([
                SelectFilter::make('channel')
                    ->options(collect(NotificationChannel::cases())->mapWithKeys(
                        fn (NotificationChannel $c) => [$c->value => $c->label()]
                    )),
                SelectFilter::make('audience')
                    ->options(collect(NotificationAudience::cases())->mapWithKeys(
                        fn (NotificationAudience $a) => [$a->value => $a->label()]
                    )),
                TernaryFilter::make('is_active')
                    ->label(__('communication.resource.is_active')),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ])->tooltip(__('admin.actions.more')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-bell')
            ->emptyStateHeading(__('communication.template.empty_heading'))
            ->emptyStateDescription(__('communication.template.empty_description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationTemplates::route('/'),
            'create' => CreateNotificationTemplate::route('/create'),
            'edit' => EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}

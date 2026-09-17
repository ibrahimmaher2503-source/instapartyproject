<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Pages;

use App\Modules\Shared\Domain\Models\AppSetting;
use App\Modules\Shared\Domain\Models\FeatureFlag;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ManageSettings extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('shared.settings.nav_label');
    }

    protected static ?int $navigationSort = 30;

    protected static string $view = 'filament.pages.manage-settings';

    public array $settings = [];

    public function mount(): void
    {
        $this->settings = AppSetting::all()->pluck('value', 'key')->toArray();
    }

    public function form(Form $schema): Form
    {
        $fields = AppSetting::all()->map(function (AppSetting $setting): TextInput {
            return TextInput::make("settings.{$setting->key}")
                ->label($setting->description ?? $setting->key)
                ->default($setting->value);
        })->all();

        return $schema->components([
            Section::make(__('shared.settings.app_settings'))
                ->schema($fields)
                ->columns(2),
        ])->statePath('settings');
    }

    public function saveSettings(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            AppSetting::where('key', $key)->update([
                'value' => $value,
                'updated_by' => Auth::id(),
            ]);
        }

        Notification::make()
            ->title(__('shared.settings.saved'))
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FeatureFlag::query())
            ->heading(__('shared.settings.feature_flags'))
            ->columns([
                TextColumn::make('key')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_enabled')
                    ->boolean()
                    ->label(__('shared.settings.enabled')),
                TextColumn::make('rollout_pct')
                    ->label(__('shared.settings.rollout_pct'))
                    ->suffix('%'),
                TextColumn::make('description')
                    ->limit(60)
                    ->placeholder('—'),
            ])
            ->actions([
                Action::make('toggle')
                    ->label(fn (FeatureFlag $record): string => $record->is_enabled ? __('shared.settings.disable') : __('shared.settings.enable'))
                    ->icon(fn (FeatureFlag $record): string => $record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (FeatureFlag $record): string => $record->is_enabled ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (FeatureFlag $record): void {
                        $record->is_enabled = ! $record->is_enabled;
                        $record->save();

                        Notification::make()
                            ->title(__('shared.settings.flag_updated'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}

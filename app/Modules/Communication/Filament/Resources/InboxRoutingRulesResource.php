<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Models\AdminInboxRoutingRule;
use App\Modules\Communication\Filament\Resources\InboxRoutingRulesResource\Pages\CreateInboxRoutingRule;
use App\Modules\Communication\Filament\Resources\InboxRoutingRulesResource\Pages\EditInboxRoutingRule;
use App\Modules\Communication\Filament\Resources\InboxRoutingRulesResource\Pages\ListInboxRoutingRules;
use App\Modules\Identity\Domain\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class InboxRoutingRulesResource extends Resource
{
    protected static ?string $model = AdminInboxRoutingRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('communication.routing_nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $schema): Form
    {
        $knownEventKeys = __('communication.routing_event_keys');

        return $schema->components([
            Select::make('event_key')
                ->label(__('communication.routing.event_key'))
                ->options($knownEventKeys)
                ->searchable()
                ->required(),

            Select::make('severity')
                ->label(__('communication.routing.severity'))
                ->options(collect(AdminInboxSeverity::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                ->required(),

            Select::make('route_to_role_id')
                ->label(__('communication.routing.route_to_role'))
                ->options(Role::query()->pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->helperText(__('communication.routing.role_or_admin_hint')),

            Select::make('route_to_admin_id')
                ->label(__('communication.routing.route_to_admin'))
                ->options(fn () => User::query()
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'vendor_manager', 'ops_manager']))
                    ->pluck('name', 'id'))
                ->searchable()
                ->nullable(),

            Toggle::make('is_active')
                ->label(__('communication.routing.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event_key')
                    ->searchable()
                    ->label(__('communication.routing.event_key')),

                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (AdminInboxSeverity $state): string => $state->color())
                    ->formatStateUsing(fn (AdminInboxSeverity $state) => $state->label())
                    ->label(__('communication.routing.severity')),

                TextColumn::make('route_target')
                    ->getStateUsing(function (AdminInboxRoutingRule $record): string {
                        if ($record->route_to_role_id !== null) {
                            return __('communication.routing_target_role', ['name' => $record->role?->name ?? "#{$record->route_to_role_id}"]);
                        }
                        if ($record->route_to_admin_id !== null) {
                            return __('communication.routing_target_admin', ['name' => $record->targetAdmin?->name ?? "#{$record->route_to_admin_id}"]);
                        }

                        return '—';
                    })
                    ->label(__('communication.routing.target')),

                ToggleColumn::make('is_active')
                    ->label(__('communication.routing.is_active')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('communication.created_at')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInboxRoutingRules::route('/'),
            'create' => CreateInboxRoutingRule::route('/create'),
            'edit' => EditInboxRoutingRule::route('/{record}/edit'),
        ];
    }
}

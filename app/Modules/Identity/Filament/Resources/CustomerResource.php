<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Application\Actions\AdminUpdateCustomerProfileAction;
use App\Modules\Identity\Application\Actions\ForceLogoutCustomerAction;
use App\Modules\Identity\Application\Actions\SuspendCustomerAction;
use App\Modules\Identity\Application\Actions\UnsuspendCustomerAction;
use App\Modules\Identity\Application\DTOs\AdminUpdateCustomerDTO;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Modules\Identity\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.vendor_management');
    }

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 15;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.customers');
    }

    public static function getModelLabel(): string
    {
        return __('identity.customer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.nav.customers');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->customers();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('identity.columns.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('identity.columns.email'))
                    ->searchable(),

                TextColumn::make('phone_e164')
                    ->label(__('identity.columns.phone'))
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('identity.columns.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label(__('identity.fields.joined_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (User $record): string => static::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('identity.columns.status'))
                    ->options([
                        'active' => __('identity.filters.status_active'),
                        'suspended' => __('identity.filters.status_suspended'),
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                self::editProfileAction(),
                self::suspendAction(),
                self::unsuspendAction(),
                self::forceLogoutAction(),
            ])
            ->bulkActions([]);
    }

    private static function editProfileAction(): Action
    {
        return Action::make('edit_profile')
            ->label(__('identity.actions.edit_profile'))
            ->icon('heroicon-o-pencil')
            ->color('primary')
            ->form([
                TextInput::make('name')
                    ->label(__('identity.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone_e164')
                    ->label(__('identity.fields.phone'))
                    ->nullable()
                    ->tel()
                    ->helperText('E.164 format: +201001234567'),
            ])
            ->fillForm(fn (User $record): array => [
                'name' => $record->name,
                'phone_e164' => $record->phone_e164,
            ])
            ->action(function (User $record, array $data): void {
                $dto = new AdminUpdateCustomerDTO(
                    name: $data['name'],
                    phoneE164: $data['phone_e164'] ?: null,
                );

                app(AdminUpdateCustomerProfileAction::class)->execute($record, $dto);

                Notification::make()
                    ->title(__('identity.notifications.profile_updated_by_admin'))
                    ->success()
                    ->send();
            })
            ->visible(fn () => auth()->user()?->can('update_customer_profile'));
    }

    private static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->label(__('identity.actions.suspend_customer'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => __('identity.modals.suspend_customer', ['name' => $record->name]))
            ->action(function (User $record): void {
                app(SuspendCustomerAction::class)->execute($record);

                Notification::make()
                    ->title(__('identity.notifications.customer_suspended'))
                    ->success()
                    ->send();
            })
            ->visible(fn (User $record): bool => $record->status === 'active'
                && $record->id !== auth()->id()
                && (bool) auth()->user()?->can('suspend_customer'));
    }

    private static function unsuspendAction(): Action
    {
        return Action::make('unsuspend')
            ->label(__('identity.actions.unsuspend_customer'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (User $record): void {
                app(UnsuspendCustomerAction::class)->execute($record);

                Notification::make()
                    ->title(__('identity.notifications.customer_unsuspended'))
                    ->success()
                    ->send();
            })
            ->visible(fn (User $record): bool => $record->status === 'suspended'
                && (bool) auth()->user()?->can('suspend_customer'));
    }

    private static function forceLogoutAction(): Action
    {
        return Action::make('force_logout')
            ->label(__('identity.actions.force_logout'))
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => __('identity.modals.force_logout_customer', ['name' => $record->name]))
            ->action(function (User $record): void {
                app(ForceLogoutCustomerAction::class)->execute($record);

                Notification::make()
                    ->title(__('identity.notifications.customer_logged_out'))
                    ->success()
                    ->send();
            })
            ->visible(fn () => (bool) auth()->user()?->can('force_logout_customer'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}

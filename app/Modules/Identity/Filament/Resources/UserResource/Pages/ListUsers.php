<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\UserResource\Pages;

use App\Modules\Geography\Domain\Contracts\GeographyRepository;
use App\Modules\Identity\Application\Actions\AdminCreateAccountAction;
use App\Modules\Identity\Application\Actions\AdminSendUserPasswordResetLinkAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('createAccount')->label(__('identity::account_management.create_account'))
            ->visible(fn (): bool => UserResource::canViewAny())
            ->modalDescription(__('identity::account_management.create_hint'))
            ->form([
                TextInput::make('name')->label(__('identity.fields.name'))->required()->maxLength(255),
                TextInput::make('email')->label(__('identity.fields.email'))->email()->required()->maxLength(255),
                TextInput::make('phone_e164')->label(__('identity.fields.phone'))->required()->regex('/^\+[1-9][0-9]{6,14}$/D'),
                Select::make('account_type')->label(__('identity::account_management.account_type'))->required()->live()->default('staff')->options([
                    'staff' => __('identity::account_management.staff'), 'customer' => __('identity.roles.customer'), 'vendor' => __('identity.roles.vendor'),
                ]),
                Select::make('role')->label(__('identity.columns.role'))->visible(fn (Get $get): bool => $get('account_type') === 'staff')
                    ->required(fn (Get $get): bool => $get('account_type') === 'staff')
                    ->options(fn (): array => Role::where('guard_name', 'web')->whereIn('name', ['admin', 'super_admin', 'booking_manager'])
                        ->pluck('name')->mapWithKeys(fn (string $role): array => [$role => __('identity.roles.'.$role)])->all()),
                TextInput::make('business_name.en')->label(__('identity.forms.business_name_en'))->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('account_type') === 'vendor')->required(fn (Get $get): bool => $get('account_type') === 'vendor'),
                TextInput::make('business_name.ar')->label(__('identity.forms.business_name_ar'))->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('account_type') === 'vendor')->required(fn (Get $get): bool => $get('account_type') === 'vendor'),
                Select::make('business_type')->label(__('identity.fields.business_type'))
                    ->options(['individual' => __('identity.business_type.individual'), 'company' => __('identity.business_type.company'),
                        'establishment' => __('identity.business_type.establishment')])
                    ->visible(fn (Get $get): bool => $get('account_type') === 'vendor')->required(fn (Get $get): bool => $get('account_type') === 'vendor'),
                Select::make('primary_governorate_id')->label(__('identity.fields.governorate_id'))->live()
                    ->afterStateUpdated(fn (Set $set) => $set('primary_city_id', null))
                    ->options(fn (): array => app(GeographyRepository::class)->activeGovernorates()
                        ->mapWithKeys(fn ($row): array => [$row->id => $row->getTranslation('name', app()->getLocale())])->all())
                    ->visible(fn (Get $get): bool => $get('account_type') === 'vendor')->required(fn (Get $get): bool => $get('account_type') === 'vendor'),
                Select::make('primary_city_id')->label(__('identity.fields.city_id'))
                    ->options(fn (Get $get): array => app(GeographyRepository::class)->listCitiesByGovernorate((int) $get('primary_governorate_id'))
                        ->mapWithKeys(fn ($row): array => [$row->id => $row->getTranslation('name', app()->getLocale())])->all())
                    ->visible(fn (Get $get): bool => $get('account_type') === 'vendor')->required(fn (Get $get): bool => $get('account_type') === 'vendor'),
            ])->action(function (array $data): void {
                $operator = Auth::guard('web')->user();
                abort_unless($operator instanceof User, 403);
                $user = app(AdminCreateAccountAction::class)->execute($operator, $data);
                $status = app(AdminSendUserPasswordResetLinkAction::class)->execute($operator, $user);
                $notification = Notification::make()->title(__('identity::account_management.created'))
                    ->body($status === Password::RESET_LINK_SENT
                        ? __('identity::account_management.invitation_requested') : __('identity::account_management.invitation_failed'));
                $status === Password::RESET_LINK_SENT ? $notification->success() : $notification->warning();
                $notification->send();
                $this->redirect(UserResource::getUrl('view', ['record' => $user]));
            })];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\UserResource\Pages;

use App\Modules\Identity\Application\Actions\AdminResendVendorVerificationAction;
use App\Modules\Identity\Application\Actions\AdminSendUserPasswordResetLinkAction;
use App\Modules\Identity\Application\Actions\AdminSyncUserStaffRolesAction;
use App\Modules\Identity\Application\Actions\AdminUpdateUserContactAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use App\Modules\Identity\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->editAccountAction(),
            $this->manageStaffRolesAction(),
            $this->sendPasswordResetAction(),
            Action::make('resendVendorVerification')->label(__('identity::account_management.resend_verification'))
                ->visible(fn (): bool => $this->authorizedOperator() !== null && $this->record->hasRole('vendor', 'web')
                    && $this->record->status === 'active' && filled($this->record->email) && ! $this->record->hasVerifiedEmail())
                ->requiresConfirmation()->modalDescription(__('identity::account_management.verification_hint'))
                ->action(function (): void {
                    $operator = $this->authorizedOperator();
                    abort_unless($operator instanceof User, 403);
                    $accepted = app(AdminResendVendorVerificationAction::class)->execute($operator, $this->record);
                    $notification = Notification::make()->title($accepted
                        ? __('identity::account_management.verification_requested') : __('identity::account_management.delivery_failed'));
                    $accepted ? $notification->success() : $notification->danger();
                    $notification->send();
                }),
        ];
    }

    private function editAccountAction(): Action
    {
        return Action::make('editAccount')
            ->label(__('identity.actions.edit_user_account'))
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (): bool => $this->canManageContact())
            ->form([
                TextInput::make('name')
                    ->label(__('identity.forms.user_name'))
                    ->default(fn (): string => $this->record->name)
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('identity.forms.user_email'))
                    ->default(fn (): ?string => $this->record->email)
                    ->nullable()
                    ->required(fn (): bool => $this->record->hasAnyRole(['admin', 'super_admin', 'booking_manager', 'vendor']))
                    ->email()
                    ->maxLength(255)
                    ->rule(Rule::unique('users', 'email')->ignore($this->record->id)),
                TextInput::make('phone_e164')
                    ->label(__('identity.forms.user_phone'))
                    ->default(fn (): string => $this->record->phone_e164)
                    ->required()
                    ->tel()
                    ->maxLength(20)
                    ->rule('regex:/^\+[1-9][0-9]{6,14}$/D')
                    ->rule(Rule::unique('users', 'phone_e164')->ignore($this->record->id)),
            ])
            ->action(function (array $data): void {
                $operator = $this->authorizedOperator();
                abort_unless($operator instanceof User, 403);

                app(AdminUpdateUserContactAction::class)->execute(
                    $operator,
                    $this->record,
                    (string) $data['name'],
                    filled($data['email'] ?? null) ? (string) $data['email'] : null,
                    (string) $data['phone_e164'],
                );

                Notification::make()
                    ->title(__('identity.notifications.user_account_updated'))
                    ->success()
                    ->send();

                $this->refreshFormData(['name', 'email', 'phone_e164']);
            });
    }

    private function manageStaffRolesAction(): Action
    {
        return Action::make('manageStaffRoles')
            ->label(__('identity.actions.manage_staff_roles'))
            ->icon('heroicon-o-shield-check')
            ->color('warning')
            ->visible(fn (): bool => $this->canManageStaffRoles())
            ->form([
                Select::make('roles')
                    ->label(__('identity.forms.staff_roles'))
                    ->options(fn (): array => Role::query()
                        ->where('guard_name', 'web')
                        ->whereIn('name', ['admin', 'super_admin', 'booking_manager'])
                        ->orderBy('name')
                        ->pluck('name', 'name')
                        ->mapWithKeys(fn (string $role): array => [
                            $role => __('identity.roles.'.str_replace('.', '_', $role)),
                        ])
                        ->all())
                    ->default(fn (): array => $this->record->roles()
                        ->where('guard_name', 'web')
                        ->whereIn('name', ['admin', 'super_admin', 'booking_manager'])
                        ->pluck('name')
                        ->all())
                    ->multiple()
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(function (array $data): void {
                $operator = $this->authorizedOperator();
                abort_unless($operator instanceof User, 403);

                app(AdminSyncUserStaffRolesAction::class)->execute(
                    $operator,
                    $this->record,
                    array_values($data['roles'] ?? []),
                );

                Notification::make()
                    ->title(__('identity.notifications.user_roles_updated'))
                    ->success()
                    ->send();

                $this->record->refresh();
            });
    }

    private function sendPasswordResetAction(): Action
    {
        return Action::make('sendPasswordReset')
            ->label(__('identity.actions.send_password_reset'))
            ->icon('heroicon-o-key')
            ->color('gray')
            ->visible(fn (): bool => $this->canSendPasswordReset())
            ->requiresConfirmation()
            ->modalDescription(__('identity.account_management.reset_confirmation'))
            ->action(function (): void {
                $operator = $this->authorizedOperator();
                abort_unless($operator instanceof User, 403);

                $status = app(AdminSendUserPasswordResetLinkAction::class)->execute($operator, $this->record);
                $notification = Notification::make()->title(match ($status) {
                    Password::RESET_LINK_SENT => __('identity.account_management.reset_link_requested'),
                    Password::RESET_THROTTLED => __('identity.account_management.reset_throttled'),
                    default => __('identity.account_management.reset_unavailable'),
                });

                if ($status === Password::RESET_LINK_SENT) {
                    $notification->success();
                } else {
                    $notification->danger();
                }

                $notification->send();
            });
    }

    private function authorizedOperator(): ?User
    {
        $operator = Auth::guard('web')->user();

        return $operator instanceof User
            && $operator->status === 'active'
            && $operator->hasRole('super_admin', 'web')
                ? $operator
                : null;
    }

    private function canManageContact(): bool
    {
        $operator = $this->authorizedOperator();

        return $operator !== null
            && ! $this->record->is($operator)
            && (! $this->record->hasRole('vendor', 'web') || $this->record->vendorProfile()->exists());
    }

    private function canManageStaffRoles(): bool
    {
        $operator = $this->authorizedOperator();
        $currentRoleNames = $this->record->roles()->where('guard_name', 'web')->pluck('name')->all();

        return $operator !== null
            && ! $this->record->is($operator)
            && $currentRoleNames !== []
            && array_intersect($currentRoleNames, ['admin', 'super_admin', 'booking_manager']) !== []
            && array_diff($currentRoleNames, ['admin', 'super_admin', 'booking_manager']) === []
            && ! $this->record->hasRole('customer', 'web')
            && ! $this->record->hasRole('vendor', 'web');
    }

    private function canSendPasswordReset(): bool
    {
        $operator = $this->authorizedOperator();
        $isStaff = $this->record->hasAnyRole(['admin', 'super_admin', 'booking_manager']);
        $isVendor = $this->record->hasRole('vendor', 'web');
        $isCustomer = $this->record->hasRole('customer', 'web');
        $vendorProfile = $isVendor ? $this->record->vendorProfile : null;

        return $operator !== null
            && filled($this->record->email)
            && $this->record->status === 'active'
            && count(array_filter([$isStaff, $isVendor, $isCustomer])) === 1
            && (! $isVendor || ($vendorProfile !== null && ! ($vendorProfile->approval_status instanceof SuspendedState)));
    }
}

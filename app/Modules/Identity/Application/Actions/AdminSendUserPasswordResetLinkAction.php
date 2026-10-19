<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Auth\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class AdminSendUserPasswordResetLinkAction
{
    public function execute(User $actor, User $target): string
    {
        $actor = User::query()->findOrFail($actor->getKey());

        if (
            (int) Auth::guard('web')->id() !== (int) $actor->id
            || $actor->status !== 'active'
            || ! $actor->hasRole('super_admin', 'web')
        ) {
            throw new AuthorizationException(__('identity.account_management.not_allowed'));
        }

        $target = User::query()->findOrFail($target->getKey());
        $isVendor = $target->hasRole('vendor', 'web');
        $isStaff = $target->hasAnyRole(['admin', 'super_admin', 'booking_manager']);

        if (
            blank($target->email)
            || $target->status !== 'active'
            || $isVendor === $isStaff
            || ($isVendor && (! $target->vendorProfile || $target->vendorProfile->approval_status instanceof SuspendedState))
        ) {
            throw new AuthorizationException(__('identity.account_management.reset_unavailable'));
        }

        $panel = Filament::getPanel($isVendor ? 'vendor' : 'admin');

        if (! $target->canAccessPanel($panel)) {
            throw new AuthorizationException(__('identity.account_management.reset_unavailable'));
        }

        $actorId = (int) $actor->id;

        $status = Password::broker($panel->getAuthPasswordBroker())->sendResetLink(
            ['email' => $target->email],
            function (CanResetPassword $user, string $token) use ($actorId, $target, $panel): void {
                $operator = User::query()->find($actorId);

                if (
                    ! ($user instanceof User)
                    || (int) $user->id !== (int) $target->id
                    || ! ($user instanceof FilamentUser)
                    || $operator === null
                    || (int) Auth::guard('web')->id() !== $actorId
                    || $operator->status !== 'active'
                    || ! $operator->hasRole('super_admin', 'web')
                    || $user->status !== 'active'
                    || ! $user->canAccessPanel($panel)
                    || $user->hasRole('vendor', 'web') === $user->hasAnyRole(['admin', 'super_admin', 'booking_manager'])
                    || ($user->hasRole('vendor', 'web')
                        && (! $user->vendorProfile || $user->vendorProfile->approval_status instanceof SuspendedState))
                ) {
                    throw new AuthorizationException(__('identity.account_management.reset_unavailable'));
                }

                $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                $notification->url = $panel->getResetPasswordUrl($token, $user);
                $user->notify($notification);
                Event::dispatch(new PasswordResetLinkSent($user));
            },
        );

        if ($status === Password::RESET_LINK_SENT) {
            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => User::class,
                'auditable_id' => $target->id,
                'user_id' => $actor->id,
                'action' => 'user_password_reset_link_requested',
                'changes' => json_encode(['broker_status' => $status], JSON_THROW_ON_ERROR),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);
        }

        return $status;
    }
}

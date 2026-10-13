<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Communication\Domain\Contracts\EmailTransportReadiness;
use App\Modules\Identity\Application\DTOs\RequestPasswordResetDTO;
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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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
        $isCustomer = $target->hasRole('customer', 'web');

        if (
            blank($target->email)
            || $target->status !== 'active'
            || count(array_filter([$isVendor, $isStaff, $isCustomer])) !== 1
            || ($isVendor && (! $target->vendorProfile || $target->vendorProfile->approval_status instanceof SuspendedState))
        ) {
            throw new AuthorizationException(__('identity.account_management.reset_unavailable'));
        }

        $panel = $isCustomer ? null : Filament::getPanel($isVendor ? 'vendor' : 'admin');

        if ($panel !== null && ! $target->canAccessPanel($panel)) {
            throw new AuthorizationException(__('identity.account_management.reset_unavailable'));
        }

        $actorId = (int) $actor->id;

        try {
            if (! app(EmailTransportReadiness::class)->isConfigured()) {
                throw new RuntimeException('Mail transport unavailable');
            }
            if ($isCustomer) {
                $key = 'admin-customer-password-reset:'.$target->id;
                if (RateLimiter::tooManyAttempts($key, 1)) {
                    return Password::RESET_THROTTLED;
                }
                RateLimiter::hit($key, 60);
                app(RequestPasswordResetAction::class)->execute(new RequestPasswordResetDTO(
                    identifier: $target->email, channel: 'email', locale: $target->preferred_locale,
                ));
                $status = Password::RESET_LINK_SENT;
            } else {
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
            }
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable) {
            $status = 'delivery_failed';
        }

        if (in_array($status, [Password::RESET_LINK_SENT, 'delivery_failed'], true)) {
            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => User::class,
                'auditable_id' => $target->id,
                'user_id' => $actor->id,
                'action' => $status === 'delivery_failed' ? 'user_password_reset_delivery_failed' : 'user_password_reset_link_requested',
                'changes' => json_encode(['broker_status' => $status], JSON_THROW_ON_ERROR),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);
        }

        return $status;
    }
}

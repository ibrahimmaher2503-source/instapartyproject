<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    /**
     * @return array{user: User, token: ?string}
     */
    public function execute(string $login, string $password, ?string $ip = null, bool $useToken = true, ?string $deviceName = null): array
    {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_e164';

        $user = User::where($field, $login)->first();

        if ($user === null
            || $user->hasAnyRole(['admin', 'super_admin', 'booking_manager', 'panel_user'])
            || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => __('identity.invalid_credentials'),
            ]);
        }

        if ($user->status === 'suspended') {
            throw ValidationException::withMessages([
                'login' => __('identity.account_suspended'),
            ]);
        }

        if ($user->hasRole('customer') && $user->phone_verified_at === null) {
            throw ValidationException::withMessages([
                'login' => __('identity.customer_phone_verification_required'),
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        $token = $useToken ? $user->createToken($deviceName ?? 'api')->plainTextToken : null;

        return ['user' => $user, 'token' => $token];
    }
}

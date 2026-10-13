<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Models\TwoFactorSecret;
use App\Modules\Identity\Domain\Models\User;

final class AdminMfaSession
{
    public static function token(User $user, #[\SensitiveParameter] string $secret): string
    {
        return $user->id.':'.hash('sha256', $secret.':'.$user->password);
    }

    public static function hasProof(User $user): bool
    {
        $mfa = TwoFactorSecret::where('user_id', $user->id)->whereNotNull('confirmed_at')->first();

        return $mfa === null ? ! config('app.admin_mfa_required')
            : session('admin_mfa_verified') === self::token($user, $mfa->secret_encrypted);
    }
}

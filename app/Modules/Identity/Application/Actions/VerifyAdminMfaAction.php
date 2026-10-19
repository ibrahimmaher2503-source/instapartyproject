<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\TwoFactorSecret;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

final class VerifyAdminMfaAction
{
    public function execute(User $user, #[\SensitiveParameter] string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $mfa = TwoFactorSecret::where('user_id', $user->id)->lockForUpdate()->first();
            if ($mfa === null || $mfa->confirmed_at === null) {
                return false;
            }

            if (preg_match('/^[0-9]{6}$/D', $code) === 1) {
                $step = (new Google2FA)->verifyKeyNewer($mfa->secret_encrypted, $code, $mfa->last_used_timestep ?? 0, 1);
                if ($step === false) {
                    return false;
                }
                $mfa->last_used_timestep = (int) $step;
            } else {
                $hashes = $mfa->recovery_codes_encrypted ?? [];
                $matched = null;
                foreach ($hashes as $index => $hash) {
                    if (Hash::check($code, $hash)) {
                        $matched = $index;
                        break;
                    }
                }
                if ($matched === null) {
                    return false;
                }
                unset($hashes[$matched]);
                $mfa->recovery_codes_encrypted = array_values($hashes);
            }
            $mfa->save();

            return true;
        });
    }
}

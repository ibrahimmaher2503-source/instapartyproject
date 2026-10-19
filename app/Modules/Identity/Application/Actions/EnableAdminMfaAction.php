<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\TwoFactorSecret;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

final class EnableAdminMfaAction
{
    /** @return array<int, string> */
    public function execute(User $user, #[\SensitiveParameter] string $secret, string $code, #[\SensitiveParameter] string $password): array
    {
        return DB::transaction(function () use ($user, $secret, $code, $password): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless((int) Auth::guard('web')->id() === (int) $user->id
                && $user->status === 'active' && $user->canAccessPanel(filament()->getPanel('admin')), 403);
            if (! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages(['current_password' => __('account.password_invalid')]);
            }
            $step = preg_match('/^[0-9]{6}$/D', $code) === 1
                ? (new Google2FA)->verifyKeyNewer($secret, $code, 0, 1) : false;
            if ($step === false || TwoFactorSecret::where('user_id', $user->id)->whereNotNull('confirmed_at')->exists()) {
                throw ValidationException::withMessages(['mfa_code' => __('identity::security.invalid_code')]);
            }
            $codes = array_map(static fn (): string => Str::random(20), range(1, 8));
            TwoFactorSecret::updateOrCreate(['user_id' => $user->id], [
                'secret_encrypted' => $secret,
                'recovery_codes_encrypted' => array_map(static fn (string $code): string => Hash::make($code), $codes),
                'confirmed_at' => now(), 'last_used_timestep' => (int) $step,
            ]);
            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(), 'auditable_type' => User::class,
                'auditable_id' => $user->id, 'user_id' => $user->id, 'action' => 'admin_mfa_enabled',
                'changes' => json_encode(['enabled' => true], JSON_THROW_ON_ERROR),
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
            ]);

            return $codes;
        });
    }
}

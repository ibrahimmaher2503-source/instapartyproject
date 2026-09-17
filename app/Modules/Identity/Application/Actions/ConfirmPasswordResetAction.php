<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\ConfirmPasswordResetDTO;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ConfirmPasswordResetAction
{
    private const TOKEN_TTL_MINUTES = 60;

    /**
     * Verifies the hashed token in `password_reset_tokens`, sets the new
     * password on the user record, and deletes the row.
     *
     * Throws `RuntimeException('invalid_or_expired')` so the controller can
     * convert it to a 422 response without leaking which step failed.
     */
    public function execute(ConfirmPasswordResetDTO $dto): void
    {
        $row = DB::table('password_reset_tokens')
            ->where('email', $dto->identifier)
            ->first();

        if (! $row || ! Hash::check($dto->token, $row->token)) {
            throw new RuntimeException('invalid_or_expired');
        }

        // created_at is older than (now - TTL) → token has expired. Use an
        // explicit comparison: Carbon 3's diffInMinutes is signed and would
        // return a negative value for a past timestamp, silently never expiring.
        // The stale-row delete runs OUTSIDE the transaction so it survives the
        // RuntimeException (a throw inside DB::transaction rolls the delete back).
        if (Carbon::parse($row->created_at)->lt(now()->subMinutes(self::TOKEN_TTL_MINUTES))) {
            DB::table('password_reset_tokens')->where('email', $dto->identifier)->delete();
            throw new RuntimeException('invalid_or_expired');
        }

        DB::transaction(function () use ($dto): void {
            $user = filter_var($dto->identifier, FILTER_VALIDATE_EMAIL)
                ? User::where('email', $dto->identifier)->first()
                : User::where('phone_e164', $dto->identifier)->first();

            if (! $user) {
                throw new RuntimeException('invalid_or_expired');
            }

            $user->forceFill(['password' => Hash::make($dto->password)])->save();

            DB::table('password_reset_tokens')->where('email', $dto->identifier)->delete();
        });
    }
}

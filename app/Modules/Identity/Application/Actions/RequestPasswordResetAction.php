<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\RequestPasswordResetDTO;
use App\Modules\Identity\Domain\Events\PasswordResetRequested;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RequestPasswordResetAction
{
    /**
     * Records a reset token in `password_reset_tokens` (`email` column stores
     * the identifier — email OR phone_e164 — keeping the loop usable for
     * phone-only customers without a new table).
     *
     * Always returns successfully so the API response is neutral and cannot
     * be used to enumerate accounts.
     */
    public function execute(RequestPasswordResetDTO $dto): void
    {
        DB::transaction(function () use ($dto): void {
            $user = $dto->channel === 'email'
                ? User::where('email', $dto->identifier)->first()
                : User::where('phone_e164', $dto->identifier)->first();

            if (! $user) {
                return;
            }

            $token = $dto->channel === 'email'
                ? Str::random(64)
                : sprintf('%06d', random_int(0, 999999));

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $dto->identifier],
                [
                    'email' => $dto->identifier,
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ],
            );

            $userId = (int) $user->id;
            $identifier = $dto->identifier;
            $channel = $dto->channel;
            $locale = $dto->locale;

            DB::afterCommit(fn () => event(new PasswordResetRequested(
                userId: $userId,
                identifier: $identifier,
                channel: $channel,
                tokenPlain: $token,
                locale: $locale,
            )));
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

// Phase 1.0 stub — OTP verification added in Phase 5.0.
class UpdateVendorPhoneAction
{
    public function execute(User $user, string $phoneE164): void
    {
        Validator::make(['phone_e164' => $phoneE164], [
            'phone_e164' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique('users', 'phone_e164')->ignore($user->id)],
        ])->validate();
        if ($phoneE164 === $user->phone_e164) {
            return;
        }

        DB::transaction(function () use ($user, $phoneE164): void {
            $user->forceFill([
                'phone_e164' => $phoneE164,
                'phone_verified_at' => null,
            ])->save();
        });
    }
}

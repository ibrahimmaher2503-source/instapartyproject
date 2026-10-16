<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AdminCreateAccountAction
{
    public function execute(User $actor, array $data): User
    {
        $actor = User::findOrFail($actor->id);
        abort_unless((int) Auth::guard('web')->id() === (int) $actor->id
            && $actor->status === 'active' && $actor->hasRole('super_admin', 'web'), 403);
        $data['email'] = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone_e164' => ['required', 'string', 'max:20', 'regex:/^\+[1-9][0-9]{6,14}$/D', Rule::unique('users', 'phone_e164')],
            'account_type' => ['required', Rule::in(['staff', 'customer', 'vendor'])],
            'role' => ['required_if:account_type,staff', 'nullable', Rule::in(['admin', 'super_admin', 'booking_manager']),
                Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'verify_email' => ['sometimes', 'boolean'],
            'verify_phone' => ['sometimes', 'boolean'],
            'send_invite' => ['sometimes', 'boolean'],
            'business_name' => ['required_if:account_type,vendor', 'nullable', 'array'],
            'business_name.en' => ['required_if:account_type,vendor', 'nullable', 'string', 'max:255'],
            'business_name.ar' => ['required_if:account_type,vendor', 'nullable', 'string', 'max:255'],
            'business_type' => ['required_if:account_type,vendor', 'nullable', Rule::in(['individual', 'company', 'establishment'])],
            'primary_governorate_id' => ['required_if:account_type,vendor', 'nullable', 'integer', 'exists:governorates,id'],
            'primary_city_id' => ['required_if:account_type,vendor', 'nullable', 'integer', Rule::exists('cities', 'id')
                ->where('governorate_id', $data['primary_governorate_id'] ?? null)],
        ])->validate();
        $data = array_merge($data, $validated);

        return DB::transaction(function () use ($actor, $data): User {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless((int) Auth::guard('web')->id() === (int) $actor->id
                && $actor->status === 'active' && $actor->hasRole('super_admin', 'web'), 403);
            $password = 'A1!'.Str::random(61);
            $emailVerifiedAt = ($data['verify_email'] ?? false) ? now() : null;
            $phoneVerifiedAt = ($data['verify_phone'] ?? false) ? now() : null;
            if ($data['account_type'] === 'vendor') {
                $profile = app(RegisterVendorAction::class)->execute(RegisterVendorDTO::fromArray([
                    ...$data, 'password' => $password, 'password_confirmation' => $password,
                    'preferred_locale' => app()->getLocale(),
                ]));
                $user = $profile->user;
            } else {
                $user = User::create([
                    'name' => $data['name'], 'email' => $data['email'], 'phone_e164' => $data['phone_e164'],
                    'password' => $password, 'status' => 'active', 'preferred_locale' => app()->getLocale(),
                    'timezone' => 'Africa/Cairo', 'numeral_system' => 'western',
                ]);
                $user->assignRole($data['account_type'] === 'staff' ? $data['role'] : 'customer');
                if ($data['account_type'] === 'customer') {
                    CustomerProfile::create(['user_id' => $user->id, 'accepts_marketing' => false]);
                }
            }
            $user->forceFill([
                'email_verified_at' => $emailVerifiedAt,
                'phone_verified_at' => $phoneVerifiedAt,
            ])->save();
            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(), 'auditable_type' => User::class,
                'auditable_id' => $user->id, 'user_id' => $actor->id, 'action' => 'managed_account_created',
                'changes' => json_encode([
                    'account_type' => $data['account_type'],
                    'role' => $data['account_type'] === 'staff' ? $data['role'] : $data['account_type'],
                    'email_verified' => $user->email_verified_at !== null,
                    'phone_verified' => $user->phone_verified_at !== null,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'created_at' => now(),
            ]);

            return $user;
        });
    }
}

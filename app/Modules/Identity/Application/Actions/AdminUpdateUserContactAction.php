<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUpdateUserContactAction
{
    public function execute(User $actor, User $target, string $name, ?string $email, string $phoneE164): void
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

        if ($actor->is($target) || ($target->hasRole('vendor', 'web') && ! $target->vendorProfile()->exists())) {
            throw new AuthorizationException(__('identity.account_management.target_unavailable'));
        }

        $email = filled($email) ? mb_strtolower(trim($email)) : null;
        $requiresEmail = $target->hasAnyRole(['admin', 'super_admin', 'booking_manager', 'vendor']);
        $data = Validator::make(
            ['name' => $name, 'email' => $email, 'phone_e164' => $phoneE164],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => [Rule::requiredIf($requiresEmail), 'nullable', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
                'phone_e164' => [
                    'required',
                    'string',
                    'max:20',
                    'regex:/^\+[1-9][0-9]{6,14}$/D',
                    Rule::unique('users', 'phone_e164')->ignore($target->id),
                ],
            ],
        )->validate();

        $actorId = (int) $actor->id;
        $targetId = (int) $target->id;

        DB::transaction(function () use ($actorId, $targetId, $data): void {
            $lockedUsers = User::query()
                ->whereIn('id', [$actorId, $targetId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $actor = $lockedUsers->get($actorId) ?? User::query()->findOrFail($actorId);

            if (
                (int) Auth::guard('web')->id() !== (int) $actor->id
                || $actor->status !== 'active'
                || ! $actor->hasRole('super_admin', 'web')
            ) {
                throw new AuthorizationException(__('identity.account_management.not_allowed'));
            }

            $target = $lockedUsers->get($targetId) ?? User::query()->findOrFail($targetId);

            if ($actor->is($target) || ($target->hasRole('vendor', 'web') && ! $target->vendorProfile()->exists())) {
                throw new AuthorizationException(__('identity.account_management.target_unavailable'));
            }

            $old = [
                'name' => $target->name,
                'email' => $target->email,
                'phone_e164' => $target->phone_e164,
            ];
            $emailChanged = $target->email !== $data['email'];
            $phoneChanged = $target->phone_e164 !== $data['phone_e164'];

            $target->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'email_verified_at' => $emailChanged ? null : $target->email_verified_at,
                'phone_e164' => $data['phone_e164'],
                'phone_verified_at' => $phoneChanged ? null : $target->phone_verified_at,
            ])->save();

            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => User::class,
                'auditable_id' => $target->id,
                'user_id' => $actor->id,
                'action' => 'user_account_contact_updated',
                'changes' => json_encode([
                    'old' => $old,
                    'new' => [
                        'name' => $target->name,
                        'email' => $target->email,
                        'phone_e164' => $target->phone_e164,
                    ],
                ], JSON_THROW_ON_ERROR),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);
        });
    }
}

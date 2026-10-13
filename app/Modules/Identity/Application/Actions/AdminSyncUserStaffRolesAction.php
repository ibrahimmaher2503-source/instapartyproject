<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminSyncUserStaffRolesAction
{
    private const STAFF_ROLES = ['admin', 'super_admin', 'booking_manager'];

    /** @param array<int, string> $roleNames */
    public function execute(User $actor, User $target, array $roleNames): void
    {
        $actor = User::query()->findOrFail($actor->getKey());

        if (
            (int) Auth::guard('web')->id() !== (int) $actor->id
            || $actor->status !== 'active'
            || ! $actor->hasRole('super_admin', 'web')
        ) {
            throw new AuthorizationException(__('identity.account_management.not_allowed'));
        }

        $requestedRoles = array_values(array_unique(array_map('strval', $roleNames)));

        if ($requestedRoles === [] || array_diff($requestedRoles, self::STAFF_ROLES) !== []) {
            throw new AuthorizationException(__('identity.account_management.invalid_roles'));
        }

        $actorId = (int) $actor->id;
        $targetId = (int) $target->id;

        DB::transaction(function () use ($actorId, $targetId, $requestedRoles): void {
            $lockedUsers = User::query()
                ->whereIn('id', [$actorId, $targetId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $actor = $lockedUsers->get($actorId) ?? User::query()->findOrFail($actorId);
            $target = $lockedUsers->get($targetId) ?? User::query()->findOrFail($targetId);

            if (
                (int) Auth::guard('web')->id() !== (int) $actor->id
                || $actor->status !== 'active'
                || ! $actor->hasRole('super_admin', 'web')
            ) {
                throw new AuthorizationException(__('identity.account_management.not_allowed'));
            }

            $existingStaffRoles = Role::query()
                ->where('guard_name', 'web')
                ->whereIn('name', self::STAFF_ROLES)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('name')
                ->all();

            if (array_diff($requestedRoles, $existingStaffRoles) !== []) {
                throw new AuthorizationException(__('identity.account_management.invalid_roles'));
            }

            $target->load('roles');
            $currentRoles = $target->roles
                ->where('guard_name', 'web')
                ->pluck('name')
                ->values()
                ->all();

            if (
                $currentRoles === []
                || array_diff($currentRoles, self::STAFF_ROLES) !== []
                || $target->hasRole('customer', 'web')
                || $target->hasRole('vendor', 'web')
            ) {
                throw new AuthorizationException(__('identity.account_management.target_unavailable'));
            }

            $requestedAdminRoles = array_intersect($requestedRoles, ['admin', 'super_admin']);

            if (
                $target->is($actor)
                && (($target->hasRole('super_admin', 'web') && ! in_array('super_admin', $requestedRoles, true))
                    || ($target->hasRole('admin', 'web') && $requestedAdminRoles === []))
            ) {
                throw new AuthorizationException(__('identity.account_management.self_demotion_forbidden'));
            }

            if (in_array('super_admin', $currentRoles, true) && ! in_array('super_admin', $requestedRoles, true)) {
                $otherActiveAdmins = User::query()
                    ->where('status', 'active')
                    ->where('id', '!=', $target->id)
                    ->whereHas('roles', fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->where('name', 'super_admin'))
                    ->count();

                if ($otherActiveAdmins === 0) {
                    throw new AuthorizationException(__('identity.account_management.last_admin_protected'));
                }
            }

            $target->syncRoles($requestedRoles);

            DB::table('audit_logs')->insert([
                'public_id' => (string) Str::ulid(),
                'auditable_type' => User::class,
                'auditable_id' => $target->id,
                'user_id' => $actor->id,
                'action' => 'user_staff_roles_updated',
                'changes' => json_encode([
                    'old_roles' => $currentRoles,
                    'new_roles' => $requestedRoles,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);
        });
    }
}

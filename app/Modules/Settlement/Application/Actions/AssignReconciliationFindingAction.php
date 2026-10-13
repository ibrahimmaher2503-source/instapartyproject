<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AssignReconciliationFindingAction
{
    public function execute(ReconciliationFinding $finding, int $adminId): void
    {
        Gate::authorize('update', $finding);

        DB::transaction(function () use ($finding, $adminId): void {
            $finding = ReconciliationFinding::query()->lockForUpdate()->findOrFail($finding->id);
            $eligible = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('users.id', $adminId)
                ->where('users.status', 'active')
                ->whereNull('users.deleted_at')
                ->where('model_has_roles.model_type', 'App\\Modules\\Identity\\Domain\\Models\\User')
                ->where('roles.guard_name', 'web')
                ->whereIn('roles.name', ['admin', 'super_admin'])
                ->exists();

            if (! $eligible) {
                throw ValidationException::withMessages(['admin_id' => __('validation.exists', ['attribute' => __('settlement.columns.assigned_to')])]);
            }

            if ($finding->resolved_at === null) {
                $finding->update(['assigned_to_admin_id' => $adminId]);
            }
        });
    }
}

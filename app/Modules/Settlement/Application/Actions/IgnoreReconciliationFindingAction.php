<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class IgnoreReconciliationFindingAction
{
    public function execute(ReconciliationFinding $finding, string $reason): void
    {
        Gate::authorize('update', $finding);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => __('validation.required', ['attribute' => __('settlement.actions.ignore_reason')])]);
        }

        DB::transaction(function () use ($finding, $reason): void {
            $finding = ReconciliationFinding::query()->lockForUpdate()->findOrFail($finding->id);
            if ($finding->resolved_at !== null) {
                return;
            }

            $finding->update([
                'resolution' => 'ignored_known_issue',
                'resolution_note' => $reason,
                'resolved_by_user_id' => auth()->id(),
                'resolved_at' => now(),
            ]);
        });
    }
}

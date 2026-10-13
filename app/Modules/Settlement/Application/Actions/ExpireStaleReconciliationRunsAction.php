<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;

class ExpireStaleReconciliationRunsAction
{
    public function execute(): int
    {
        $cutoff = now()->subSeconds(RunReconciliationAction::MAX_RUNTIME_SECONDS);

        return ReconciliationRun::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where(fn ($running) => $running
                    ->where('status', ReconciliationStatus::Running->value)
                    ->where('started_at', '<=', $cutoff))
                    ->orWhere(fn ($queued) => $queued
                        ->where('status', ReconciliationStatus::Queued->value)
                        ->where('created_at', '<=', $cutoff));
            })
            ->update([
                'status' => ReconciliationStatus::Failed->value,
                'completed_at' => now(),
                'failure_message' => 'settlement.reconciliation_run.timed_out',
            ]);
    }
}

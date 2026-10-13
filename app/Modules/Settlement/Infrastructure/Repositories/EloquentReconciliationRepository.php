<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Repositories;

use App\Modules\Settlement\Application\DTOs\DetectedFinding;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EloquentReconciliationRepository
{
    public function createRun(
        string $scopeType,
        ?array $scopeParams,
        string $triggerKind,
        ?int $triggeredByUserId,
        ?string $idempotencyKey,
        string $correlationId,
    ): ReconciliationRun {
        return ReconciliationRun::create([
            'public_id' => Str::ulid()->toBase32(),
            'scope_type' => $scopeType,
            'scope_params' => $scopeParams,
            'status' => ReconciliationStatus::Queued->value,
            'triggered_by_user_id' => $triggeredByUserId,
            'trigger_kind' => $triggerKind,
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => $correlationId,
            'created_at' => now(),
        ]);
    }

    public function markRunning(ReconciliationRun $run): void
    {
        $run->update([
            'status' => ReconciliationStatus::Running->value,
            'started_at' => now(),
        ]);
    }

    public function recordFinding(ReconciliationRun $run, DetectedFinding $finding): ReconciliationFinding
    {
        $conditionKey = hash('sha256', json_encode([
            $finding->findingType->value,
            $finding->resourceType,
            $finding->resourceId,
            $finding->descriptionKey,
        ], JSON_THROW_ON_ERROR));

        // Keep acknowledged conditions suppressed while preserving their original evidence and run.
        $existing = ReconciliationFinding::query()
            ->where(function ($query) use ($finding, $conditionKey): void {
                $query->where('open_condition_key', $conditionKey)
                    ->orWhere(fn ($legacy) => $legacy
                        ->whereNull('open_condition_key')
                        ->where('finding_type', $finding->findingType->value)
                        ->where('resource_type', $finding->resourceType)
                        ->where('resource_id', $finding->resourceId)
                        ->where('description_key', $finding->descriptionKey)
                        ->where(fn ($status) => $status
                            ->where('resolution', 'ignored_known_issue')
                            ->orWhere(fn ($open) => $open->whereNull('resolution')->whereNull('resolved_at'))));
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            $existing->update([
                'open_condition_key' => $conditionKey,
                'last_seen_at' => now(),
                'last_seen_run_id' => $run->id,
            ]);

            return $this->recordOccurrence($run, $existing);
        }

        $record = ReconciliationFinding::query()->firstOrCreate(['open_condition_key' => $conditionKey], [
            'public_id' => Str::ulid()->toBase32(),
            'reconciliation_run_id' => $run->id,
            'finding_type' => $finding->findingType->value,
            'severity' => $finding->severity->value,
            'resource_type' => $finding->resourceType,
            'resource_id' => $finding->resourceId,
            'expected' => $finding->expected,
            'actual' => $finding->actual,
            'delta' => $finding->delta,
            'description_key' => $finding->descriptionKey,
            'description_params' => $finding->descriptionParams,
            'created_at' => now(),
            'last_seen_at' => now(),
            'last_seen_run_id' => $run->id,
        ]);

        if (! $record->wasRecentlyCreated) {
            $record->update(['last_seen_at' => now(), 'last_seen_run_id' => $run->id]);
        }

        return $this->recordOccurrence($run, $record);
    }

    private function recordOccurrence(ReconciliationRun $run, ReconciliationFinding $finding): ReconciliationFinding
    {
        DB::table('reconciliation_finding_runs')->insertOrIgnore([
            'reconciliation_finding_id' => $finding->id,
            'reconciliation_run_id' => $run->id,
            'created_at' => now(),
        ]);

        return $finding;
    }

    public function finalise(ReconciliationRun $run, ReconciliationStatus $status, array $counts): void
    {
        $run->update(array_merge($counts, [
            'status' => $status->value,
            'completed_at' => now(),
        ]));
    }
}

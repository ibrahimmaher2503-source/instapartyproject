<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\ReconciliationRunResult;
use App\Modules\Settlement\Application\DTOs\RunReconciliationInput;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Events\ReconciliationRunCompleted;
use App\Modules\Settlement\Domain\Events\ReconciliationRunStarted;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentReconciliationRepository;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RunReconciliationAction
{
    public const MAX_RUNTIME_SECONDS = 900;

    public function __construct(
        private readonly ReconcileWalletAction $reconcileWallet,
        private readonly EloquentReconciliationRepository $reconciliationRepo,
    ) {}

    public function execute(RunReconciliationInput $input): ReconciliationRunResult
    {
        $scopeHash = md5(serialize([$input->scopeType, $input->scopeParams]));
        $lockKey = "lock:reconcile:{$scopeHash}";

        $lock = Cache::lock($lockKey, self::MAX_RUNTIME_SECONDS);

        if (! $lock->get()) {
            // Another run with the same scope is in progress — return existing
            $existing = ReconciliationRun::query()
                ->where('scope_type', $input->scopeType)
                ->whereIn('status', [ReconciliationStatus::Running->value, ReconciliationStatus::Queued->value])
                ->latest('created_at')
                ->get()
                ->first(fn (ReconciliationRun $run): bool => ($run->scope_params ?? []) == $input->scopeParams);

            if ($existing !== null) {
                return new ReconciliationRunResult(
                    runId: $existing->id,
                    runPublicId: (string) $existing->public_id,
                    status: $existing->status->value,
                    walletsScanned: (int) $existing->wallets_scanned,
                    findingsCount: (int) $existing->findings_count,
                    autoRepairedCount: (int) $existing->auto_repaired_count,
                    manualReviewCount: (int) $existing->manual_review_count,
                    wasIdempotentReplay: true,
                );
            }

            throw new RuntimeException('A reconciliation run with the same scope is already locked.');
        }

        $run = null;
        $walletsScanned = 0;
        $findingsCount = 0;
        $autoRepairedCount = 0;
        $manualReviewCount = 0;
        $findingResolutions = [];

        try {
            $run = $this->reconciliationRepo->createRun(
                scopeType: $input->scopeType,
                scopeParams: $input->scopeParams ?: null,
                triggerKind: $input->triggerKind,
                triggeredByUserId: $input->triggeredByUserId,
                idempotencyKey: $input->idempotencyKey,
                correlationId: $input->correlationId,
            );

            $this->reconciliationRepo->markRunning($run);

            DB::afterCommit(fn () => event(new ReconciliationRunStarted(
                runId: $run->id,
                runPublicId: (string) $run->public_id,
                scopeType: $input->scopeType,
            )));

            $walletIds = $this->resolveWalletIds($input);
            foreach ($walletIds as $walletId) {
                if ($run->started_at->lte(now()->subSeconds(self::MAX_RUNTIME_SECONDS))) {
                    throw new RuntimeException('Reconciliation run timed out.');
                }

                $result = $this->reconcileWallet->execute($walletId, $run->id);
                $walletsScanned++;
                if ($result->findingResolutions === []) {
                    $findingsCount += $result->findingsCount;
                    $autoRepairedCount += $result->autoRepairedCount;
                    $manualReviewCount += $result->manualReviewCount;
                } else {
                    foreach ($result->findingResolutions as $findingId => $resolution) {
                        if (array_key_exists($findingId, $findingResolutions)) {
                            $previous = $findingResolutions[$findingId];
                            $autoRepairedCount -= (int) ($previous === 'auto_repaired');
                            $manualReviewCount -= (int) in_array($previous, [null, 'manual_review_required'], true);
                        } else {
                            $findingsCount++;
                        }

                        $findingResolutions[$findingId] = $resolution;
                        $autoRepairedCount += (int) ($resolution === 'auto_repaired');
                        $manualReviewCount += (int) in_array($resolution, [null, 'manual_review_required'], true);
                    }
                }

                $updated = ReconciliationRun::query()->whereKey($run->id)
                    ->where('status', ReconciliationStatus::Running->value)
                    ->where('started_at', '>', now()->subSeconds(self::MAX_RUNTIME_SECONDS))
                    ->update([
                        'wallets_scanned' => $walletsScanned,
                        'findings_count' => $findingsCount,
                        'auto_repaired_count' => $autoRepairedCount,
                        'manual_review_count' => $manualReviewCount,
                    ]);

                if (! $updated) {
                    throw new RuntimeException('Reconciliation run is no longer active.');
                }
            }

            $finalStatus = match (true) {
                $autoRepairedCount === 0 && $manualReviewCount === 0 => ReconciliationStatus::Clean,
                $manualReviewCount > 0 => ReconciliationStatus::RequiresManualReview,
                default => ReconciliationStatus::Repaired,
            };

            $updated = ReconciliationRun::query()->whereKey($run->id)
                ->where('status', ReconciliationStatus::Running->value)
                ->where('started_at', '>', now()->subSeconds(self::MAX_RUNTIME_SECONDS))
                ->update([
                    'status' => $finalStatus->value,
                    'completed_at' => now(),
                    'wallets_scanned' => $walletsScanned,
                    'findings_count' => $findingsCount,
                    'auto_repaired_count' => $autoRepairedCount,
                    'manual_review_count' => $manualReviewCount,
                ]);

            if (! $updated) {
                throw new RuntimeException('Reconciliation run is no longer active.');
            }

            DB::afterCommit(fn () => event(new ReconciliationRunCompleted(
                runId: $run->id,
                runPublicId: (string) $run->public_id,
                status: $finalStatus->value,
                walletsScanned: $walletsScanned,
                findingsCount: $findingsCount,
                autoRepairedCount: $autoRepairedCount,
                manualReviewCount: $manualReviewCount,
            )));

            return new ReconciliationRunResult(
                runId: $run->id,
                runPublicId: (string) $run->public_id,
                status: $finalStatus->value,
                walletsScanned: $walletsScanned,
                findingsCount: $findingsCount,
                autoRepairedCount: $autoRepairedCount,
                manualReviewCount: $manualReviewCount,
            );
        } catch (Throwable $exception) {
            if ($run !== null) {
                ReconciliationRun::query()->whereKey($run->id)
                    ->whereIn('status', [ReconciliationStatus::Queued->value, ReconciliationStatus::Running->value])
                    ->update([
                        'status' => ReconciliationStatus::Failed->value,
                        'completed_at' => now(),
                        'failure_message' => $run->started_at?->lte(now()->subSeconds(self::MAX_RUNTIME_SECONDS))
                            ? 'settlement.reconciliation_run.timed_out'
                            : 'settlement.reconciliation_run.failed',
                        'wallets_scanned' => $walletsScanned,
                        'findings_count' => $findingsCount,
                        'auto_repaired_count' => $autoRepairedCount,
                        'manual_review_count' => $manualReviewCount,
                    ]);
            }

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /** @return array<int> */
    private function resolveWalletIds(RunReconciliationInput $input): array
    {
        return match ($input->scopeType) {
            'wallet' => [(int) ($input->scopeParams['wallet_id'] ?? 0)],
            'vendor' => Wallet::query()
                ->where('owner_type', 'App\Modules\Identity\Domain\Models\VendorProfile')
                ->where('owner_id', (int) ($input->scopeParams['vendor_id'] ?? 0))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            'date_range' => Wallet::query()
                ->whereHas('ledgerEntries', fn ($q) => $q
                    ->whereBetween('created_at', [
                        $input->scopeParams['date_from'] ?? now()->subMonth()->toDateString(),
                        $input->scopeParams['date_to'] ?? now()->toDateString(),
                    ])
                )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            'recent_touch' => Wallet::query()
                ->whereHas('ledgerEntries', fn ($q) => $q
                    ->where('created_at', '>=', now()->sub(
                        CarbonInterval::fromString(preg_replace('/min\b/i', 'minutes', (string) ($input->scopeParams['window'] ?? '60min')) ?? '')
                    ))
                )
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            default => Wallet::query()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
        };
    }
}

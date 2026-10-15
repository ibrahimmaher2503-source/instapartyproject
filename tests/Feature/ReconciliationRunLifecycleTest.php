<?php

declare(strict_types=1);

use App\Modules\Settlement\Application\Actions\ExpireStaleReconciliationRunsAction;
use App\Modules\Settlement\Application\Actions\RunReconciliationAction;
use App\Modules\Settlement\Application\DTOs\RunReconciliationInput;
use App\Modules\Settlement\Domain\Contracts\ReconciliationDetector;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function reconciliationLifecycleInput(string $scope = 'recent_touch', array $params = ['window' => '60min']): RunReconciliationInput
{
    return new RunReconciliationInput($scope, $params, 'scheduled', null, null, (string) Str::ulid());
}

it('completes the scheduled minute alias and scans only recently touched wallets', function (): void {
    $this->freezeTime();
    $wallet = Wallet::factory()->empty()->create();
    $oldWallet = Wallet::factory()->empty()->create();
    WalletLedgerEntry::factory()->commissionCredit()->create(['wallet_id' => $wallet->id, 'amount_minor' => 100, 'created_at' => now()->subMinutes(30)]);
    WalletLedgerEntry::factory()->commissionCredit()->create(['wallet_id' => $oldWallet->id, 'amount_minor' => 100, 'created_at' => now()->subHours(2)]);

    $result = app(RunReconciliationAction::class)->execute(reconciliationLifecycleInput());

    expect($result->walletsScanned)->toBe(1);
    $run = ReconciliationRun::findOrFail($result->runId);
    expect($run->status)->not->toBe(ReconciliationStatus::Running)
        ->and($run->completed_at)->not->toBeNull()
        ->and($run->failure_message)->toBeNull();
});

it('records a terminal failure when wallet detection throws', function (): void {
    $wallet = Wallet::factory()->empty()->create();
    $detector = Mockery::mock(ReconciliationDetector::class);
    $detector->shouldReceive('detectCacheDrift')->once()->with($wallet->id)->andThrow(new RuntimeException('detector unavailable'));
    app()->instance(ReconciliationDetector::class, $detector);

    expect(fn () => app(RunReconciliationAction::class)->execute(reconciliationLifecycleInput('wallet', ['wallet_id' => $wallet->id])))
        ->toThrow(RuntimeException::class, 'detector unavailable');

    $run = ReconciliationRun::query()->latest('id')->firstOrFail();
    expect($run->status)->toBe(ReconciliationStatus::Failed)
        ->and($run->completed_at)->not->toBeNull()
        ->and($run->failure_message)->toBe('settlement.reconciliation_run.failed');
});

it('completes an empty full scan without leaving a running record', function (): void {
    $result = app(RunReconciliationAction::class)->execute(reconciliationLifecycleInput('all', []));

    expect($result->status)->toBe('clean')
        ->and($result->walletsScanned)->toBe(0)
        ->and(ReconciliationRun::findOrFail($result->runId)->completed_at)->not->toBeNull();
});

it('does not overwrite a timeout with success when a wallet scan returns late', function (): void {
    $this->freezeTime();
    $wallet = Wallet::factory()->empty()->create();
    $detector = Mockery::mock(ReconciliationDetector::class);
    $detector->shouldReceive('detectCacheDrift')->once()->with($wallet->id)->andReturnUsing(function (): array {
        $this->travel(16)->minutes();
        app(ExpireStaleReconciliationRunsAction::class)->execute();

        return [];
    });
    $detector->shouldReceive('detectOrphanedRefunds', 'detectOrphanedLedgerEntries', 'detectUnbalancedGroups', 'detectCommissionsWithoutSnapshot', 'detectWithdrawalsWithoutReserve', 'detectNegativeVendorBalances', 'detectCurrencyMismatches')->andReturn([]);
    app()->instance(ReconciliationDetector::class, $detector);

    expect(fn () => app(RunReconciliationAction::class)->execute(reconciliationLifecycleInput('wallet', ['wallet_id' => $wallet->id])))
        ->toThrow(RuntimeException::class);

    $run = ReconciliationRun::query()->latest('id')->firstOrFail();
    expect($run->status)->toBe(ReconciliationStatus::Failed)
        ->and($run->failure_message)->toBe('settlement.reconciliation_run.timed_out')
        ->and($run->completed_at)->not->toBeNull();
});

it('expires abandoned active runs without changing completed or recent runs', function (): void {
    $this->freezeTime();
    $attributes = ['scope_type' => 'recent_touch', 'scope_params' => ['window' => '60min'], 'trigger_kind' => 'scheduled', 'correlation_id' => (string) Str::ulid(), 'created_at' => now()->subMinutes(20)];
    $stale = ReconciliationRun::create($attributes + ['public_id' => (string) Str::ulid(), 'status' => 'running', 'started_at' => now()->subMinutes(20), 'wallets_scanned' => 2]);
    $queued = ReconciliationRun::create($attributes + ['public_id' => (string) Str::ulid(), 'status' => 'queued']);
    $recent = ReconciliationRun::create($attributes + ['public_id' => (string) Str::ulid(), 'status' => 'running', 'started_at' => now()->subMinute()]);
    $completed = ReconciliationRun::create($attributes + ['public_id' => (string) Str::ulid(), 'status' => 'clean', 'started_at' => now()->subMinutes(20), 'completed_at' => now()->subMinutes(19)]);

    expect(app(ExpireStaleReconciliationRunsAction::class)->execute())->toBe(2);
    expect($stale->fresh()->status)->toBe(ReconciliationStatus::Failed)
        ->and($stale->fresh()->wallets_scanned)->toBe(2)
        ->and($stale->fresh()->failure_message)->toBe('settlement.reconciliation_run.timed_out')
        ->and($queued->fresh()->status)->toBe(ReconciliationStatus::Failed)
        ->and($recent->fresh()->status)->toBe(ReconciliationStatus::Running)
        ->and($completed->fresh()->status)->toBe(ReconciliationStatus::Clean);
});

it('does not start a run or release another owners scope lock', function (): void {
    $input = reconciliationLifecycleInput();
    $key = 'lock:reconcile:'.md5(serialize([$input->scopeType, $input->scopeParams]));
    $lock = Cache::lock($key, 900);
    $lock->get();

    try {
        expect(fn () => app(RunReconciliationAction::class)->execute($input))->toThrow(RuntimeException::class);
        expect(ReconciliationRun::query()->count())->toBe(0)
            ->and(Cache::lock($key, 900)->get())->toBeFalse();
    } finally {
        $lock->release();
    }
});

it('counts one shared unbalanced group once across wallets and repeated runs', function (): void {
    $group = LedgerTransactionGroup::create(['public_id' => (string) Str::ulid(), 'kind' => 'payment_capture', 'currency' => 'EGP', 'correlation_id' => (string) Str::ulid(), 'posted_at' => now(), 'created_at' => now()]);
    foreach ([Wallet::factory()->withBalance(100)->create(), Wallet::factory()->withBalance(100)->create()] as $wallet) {
        WalletLedgerEntry::factory()->commissionCredit()->create(['wallet_id' => $wallet->id, 'amount_minor' => 100, 'transaction_group_id' => $group->id]);
    }

    $runs = [];
    foreach ([1, 2, 3] as $attempt) {
        $result = app(RunReconciliationAction::class)->execute(reconciliationLifecycleInput('all', []));
        $run = ReconciliationRun::findOrFail($result->runId);
        $runs[] = $run;
        expect($result->walletsScanned)->toBe(2)
            ->and($result->findingsCount)->toBe(1)
            ->and($result->manualReviewCount)->toBe(1)
            ->and($result->autoRepairedCount)->toBe(0)
            ->and($run->findings_count)->toBe(1)
            ->and($run->manual_review_count)->toBe(1)
            ->and(ReconciliationFinding::query()->count())->toBe(1);
    }

    foreach ($runs as $run) {
        expect($run->observedFindings()->count())->toBe(1);
    }
});

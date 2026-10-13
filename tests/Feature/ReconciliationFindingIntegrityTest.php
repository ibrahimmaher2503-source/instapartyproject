<?php

declare(strict_types=1);

use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Infrastructure\Gateways\DatabaseNotificationAdapter;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\AssignReconciliationFindingAction;
use App\Modules\Settlement\Application\Actions\IgnoreReconciliationFindingAction;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\DetectedFinding;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Application\Listeners\NotifyAdminOnHighSeverityFindingListener;
use App\Modules\Settlement\Database\Factories\WithdrawalFactory;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use App\Modules\Settlement\Domain\Events\ReconciliationFindingRaised;
use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use App\Modules\Settlement\Filament\Pages\ReconciliationDashboard;
use App\Modules\Settlement\Infrastructure\Detectors\EloquentReconciliationDetector;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentReconciliationRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->freezeTime();
    $this->wallet = Wallet::factory()->empty()->create();
    $this->repository = app(EloquentReconciliationRepository::class);
    $this->run = $this->repository->createRun('wallet', ['wallet_id' => $this->wallet->id], 'manual', null, null, (string) Str::ulid());
    $this->detection = new DetectedFinding(
        ReconciliationFindingType::NegativeVendorBalance,
        ReconciliationFindingSeverity::High,
        'wallet', $this->wallet->id,
        ['balance_minor' => '>= 0'], ['balance_minor' => -100], ['balance_minor' => -100],
        'settlement.findings.negative_vendor_balance',
    );
});

it('retains one open condition and original evidence across repeated runs while tracking its last appearance', function (): void {
    $first = $this->repository->recordFinding($this->run, $this->detection);
    $original = $first->only(['reconciliation_run_id', 'expected', 'actual', 'delta', 'created_at']);
    $this->travel(1)->day();
    $nextRun = $this->repository->createRun('all', null, 'scheduled', null, null, (string) Str::ulid());
    $repeat = $this->repository->recordFinding($nextRun, $this->detection);
    $sameRunRepeat = $this->repository->recordFinding($nextRun, $this->detection);

    expect($repeat->id)->toBe($first->id)
        ->and($sameRunRepeat->id)->toBe($first->id)
        ->and(ReconciliationFinding::count())->toBe(1)
        ->and($repeat->only(array_keys($original)))->toEqual($original)
        ->and($repeat->last_seen_run_id)->toBe($nextRun->id)
        ->and($repeat->last_seen_at->equalTo(now()))->toBeTrue();

    $other = new DetectedFinding(ReconciliationFindingType::WithdrawalWithoutReserveEntry, ReconciliationFindingSeverity::High, 'withdrawal', 999, ['valid_reserve_entry' => true], ['valid_reserve_entry' => false], null, 'settlement.findings.withdrawal_without_reserve');
    expect($this->repository->recordFinding($nextRun, $other)->id)->not->toBe($first->id)
        ->and(ReconciliationFinding::count())->toBe(2);
});

it('adopts the same legacy finding repeatedly without deleting or rewriting historical duplicates', function (): void {
    $legacy = $this->repository->recordFinding($this->run, $this->detection);
    $legacy->update(['open_condition_key' => null, 'last_seen_at' => null, 'last_seen_run_id' => null]);
    $duplicate = $legacy->replicate(['public_id']);
    $duplicate->public_id = (string) Str::ulid();
    $duplicate->save();
    $duplicateEvidence = $duplicate->getAttributes();

    expect(ReconciliationFinding::query()->canonicalConditions()->count())->toBe(1)
        ->and(app(ReconciliationDashboard::class)->getOpenHighFindingsCount())->toBe(1);

    $first = $this->repository->recordFinding($this->run, $this->detection);
    $repeat = $this->repository->recordFinding($this->run, $this->detection);

    expect($first->id)->toBe($legacy->id)
        ->and($repeat->id)->toBe($legacy->id)
        ->and(ReconciliationFinding::count())->toBe(2)
        ->and($duplicate->fresh()->getAttributes())->toEqual($duplicateEvidence);
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('update_reconciliation::finding', 'web'));
    $this->actingAs($admin);
    app(IgnoreReconciliationFindingAction::class)->execute($first, 'Accepted legacy condition');
    expect(ReconciliationFinding::query()->canonicalConditions()->count())->toBe(0)
        ->and(app(ReconciliationDashboard::class)->getOpenHighFindingsCount())->toBe(0)
        ->and(ReconciliationFinding::count())->toBe(2)
        ->and($duplicate->fresh()->getAttributes())->toEqual($duplicateEvidence);
});

it('keeps repeated findings visible in every observed run after a later scan replaces the last seen run', function (): void {
    $finding = $this->repository->recordFinding($this->run, $this->detection);
    $runB = $this->repository->createRun('all', null, 'scheduled', null, null, (string) Str::ulid());
    $runC = $this->repository->createRun('all', null, 'scheduled', null, null, (string) Str::ulid());
    $this->repository->recordFinding($runB, $this->detection);
    $this->repository->recordFinding($runB, $this->detection);
    $this->repository->recordFinding($runC, $this->detection);

    expect($finding->fresh()->last_seen_run_id)->toBe($runC->id)
        ->and((int) $finding->fresh()->reconciliation_run_id)->toBe($this->run->id)
        ->and(DB::table('reconciliation_finding_runs')->where('reconciliation_finding_id', $finding->id)->count())->toBe(3);
    expect($this->run->fresh()->findings()->where('reconciliation_findings.id', $finding->id)->exists())->toBeTrue();
    foreach ([$this->run, $runB, $runC] as $run) {
        expect(DB::table('reconciliation_finding_runs')->where('reconciliation_finding_id', $finding->id)->where('reconciliation_run_id', $run->id)->count())->toBe(1)
            ->and($run->fresh()->observedFindings()->where('reconciliation_findings.id', $finding->id)->exists())->toBeTrue();
    }
});

it('keeps an ignored known condition suppressed on the next detection and preserves who acknowledged it and why', function (): void {
    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('update_reconciliation::finding', 'web'));
    $this->actingAs($admin);
    $finding = $this->repository->recordFinding($this->run, $this->detection);
    app(IgnoreReconciliationFindingAction::class)->execute($finding, '  Accepted known legacy condition  ');
    $repeat = $this->repository->recordFinding($this->run, $this->detection);

    expect($repeat->id)->toBe($finding->id)
        ->and(ReconciliationFinding::count())->toBe(1)
        ->and($finding->fresh()->resolution)->toBe('ignored_known_issue')
        ->and($finding->fresh()->resolution_note)->toBe('Accepted known legacy condition')
        ->and((int) $finding->fresh()->resolved_by_user_id)->toBe($admin->id);
});

it('requires update permission and an active eligible admin before assigning financial findings', function (): void {
    $finding = $this->repository->recordFinding($this->run, $this->detection);
    $actor = User::factory()->create();
    $target = User::factory()->superAdmin()->create();
    $this->actingAs($actor);
    expect(fn () => app(AssignReconciliationFindingAction::class)->execute($finding, $target->id))->toThrow(AuthorizationException::class);
    expect(fn () => app(IgnoreReconciliationFindingAction::class)->execute($finding, 'known issue'))->toThrow(AuthorizationException::class);
    $actor->givePermissionTo(Permission::findOrCreate('update_reconciliation::finding', 'web'));
    app(AssignReconciliationFindingAction::class)->execute($finding, $target->id);
    expect($finding->fresh()->assigned_to_admin_id)->toBe($target->id);
    $target->update(['status' => 'suspended']);
    expect(fn () => app(AssignReconciliationFindingAction::class)->execute($finding, $target->id))->toThrow(ValidationException::class);
    expect(fn () => app(IgnoreReconciliationFindingAction::class)->execute($finding, '   '))->toThrow(ValidationException::class);
});

it('detects a negative vendor ledger despite a positive cache and ignores a negative platform wallet', function (): void {
    $this->wallet->update(['balance_minor' => 1000]);
    WalletLedgerEntry::factory()->refundDebit()->create(['wallet_id' => $this->wallet->id, 'amount_minor' => 200]);
    $detector = app(EloquentReconciliationDetector::class);
    expect($detector->detectNegativeVendorBalances($this->wallet->id)[0]->actual)->toBe(['balance_minor' => -200]);
    $platform = Wallet::factory()->empty()->create(['owner_type' => 'platform_account', 'owner_id' => 9001]);
    WalletLedgerEntry::factory()->refundDebit()->create(['wallet_id' => $platform->id, 'amount_minor' => 200]);
    expect($detector->detectNegativeVendorBalances($platform->id))->toBe([]);
});

it('recognizes the reserve posted at request time and never debits it a second time on approval or detection', function (): void {
    $this->wallet->update(['balance_minor' => 100000]);
    WalletLedgerEntry::factory()->commissionCredit()->create(['wallet_id' => $this->wallet->id, 'amount_minor' => 100000]);
    $vendor = $this->wallet->owner;
    $withdrawal = app(RequestWithdrawalAction::class)->execute(new RequestWithdrawalDto(
        vendorProfileId: $vendor->id, requestedByUserId: $vendor->user_id, amountMinor: 20000, currency: 'EGP',
        bankAccount: new BankAccountSnapshot('Test Vendor', 'EG380019000500000000263180002', 'Test Bank', 'TESTEGCX'),
    ));
    $detector = app(EloquentReconciliationDetector::class);
    expect($detector->detectWithdrawalsWithoutReserve($this->wallet->id))->toBe([]);
    $ledgerBefore = DB::table('wallet_ledger')->orderBy('id')->get()->toJson();
    $groupBefore = DB::table('ledger_transaction_groups')->orderBy('id')->get()->toJson();
    app(ApproveWithdrawalAction::class)->execute($withdrawal, User::factory()->asAdmin()->create());
    expect($detector->detectWithdrawalsWithoutReserve($this->wallet->id))->toBe([])
        ->and(DB::table('wallet_ledger')->orderBy('id')->get()->toJson())->toBe($ledgerBefore)
        ->and(DB::table('ledger_transaction_groups')->orderBy('id')->get()->toJson())->toBe($groupBefore);
});

it('rejects an unreserved pending withdrawal without changing financial history or approval state', function (): void {
    $withdrawal = WithdrawalFactory::new()->pending()->create(['vendor_profile_id' => $this->wallet->owner_id]);
    $admin = User::factory()->asAdmin()->create();
    $original = $withdrawal->getAttributes();
    $ledgerBefore = DB::table('wallet_ledger')->orderBy('id')->get()->toJson();
    $auditBefore = DB::table('audit_logs')->orderBy('id')->get()->toJson();
    $groupsBefore = DB::table('ledger_transaction_groups')->orderBy('id')->get()->toJson();

    expect(fn () => app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin))->toThrow(ValidationException::class);
    expect($withdrawal->fresh()->getAttributes())->toEqual($original)
        ->and(DB::table('wallet_ledger')->orderBy('id')->get()->toJson())->toBe($ledgerBefore)
        ->and(DB::table('audit_logs')->orderBy('id')->get()->toJson())->toBe($auditBefore)
        ->and(DB::table('ledger_transaction_groups')->orderBy('id')->get()->toJson())->toBe($groupsBefore)
        ->and(DB::table('idempotency_keys')->where('key', 'wd_approve:'.$withdrawal->id)->exists())->toBeFalse();
});

it('flags missing and wrongly owned or denominated reserve evidence without fabricating financial repairs', function (string $fault): void {
    $withdrawal = WithdrawalFactory::new()->approved()->create(['vendor_profile_id' => $this->wallet->owner_id, 'requested_amount_minor' => 20000]);
    if ($fault !== 'missing') {
        $group = LedgerTransactionGroup::create(['public_id' => (string) Str::ulid(), 'kind' => $fault === 'group_kind' ? 'manual_adjustment' : 'withdrawal_reserve', 'currency' => 'EGP', 'correlation_id' => (string) Str::ulid(), 'posted_at' => now()]);
        $entry = WalletLedgerEntry::factory()->create([
            'wallet_id' => $fault === 'owner' ? Wallet::factory()->empty()->create()->id : $this->wallet->id,
            'currency' => $fault === 'currency' ? 'USD' : 'EGP', 'amount_minor' => 20000,
            'direction' => 'debit', 'entry_type' => 'withdrawal_reserve', 'transaction_group_id' => $group->id,
            'related_entity_type' => 'withdrawal', 'related_entity_id' => $withdrawal->id,
            'posted_at' => $fault === 'unposted' ? null : now(),
        ]);
        $withdrawal->update(['reserved_ledger_entry_id' => $entry->id]);
    }
    $before = DB::table('wallet_ledger')->orderBy('id')->get()->toJson();
    $findings = app(EloquentReconciliationDetector::class)->detectWithdrawalsWithoutReserve($this->wallet->id);
    expect($findings)->toHaveCount(1)
        ->and($findings[0]->resourceId)->toBe($withdrawal->id)
        ->and(DB::table('wallet_ledger')->orderBy('id')->get()->toJson())->toBe($before);
})->with(['missing', 'owner', 'currency', 'group_kind', 'unposted']);

it('dispatches one actionable bell alert per active eligible admin and makes it visible to Filament', function (): void {
    Queue::fake();
    $admin = User::factory()->asAdmin()->create(['preferred_locale' => 'en']);
    $superAdmin = User::factory()->superAdmin()->create(['preferred_locale' => 'ar']);
    User::factory()->asAdmin()->create(['status' => 'suspended']);
    $deleted = User::factory()->asAdmin()->create();
    $deleted->delete();
    User::factory()->asCustomer()->create();
    $finding = $this->repository->recordFinding($this->run, $this->detection);
    $event = new ReconciliationFindingRaised($finding->id, $finding->public_id, $this->detection->findingType, $this->detection->severity, 'wallet', $this->wallet->id);
    $listener = app(NotifyAdminOnHighSeverityFindingListener::class);
    $listener->handle($event);
    $listener->handle($event);
    $dispatches = NotificationDispatch::query()->where('reference_type', 'reconciliation_finding')->where('reference_id', $finding->id)->get();
    expect($dispatches)->toHaveCount(2)
        ->and($dispatches->pluck('user_id')->map(fn ($id) => (int) $id)->sort()->values()->all())->toBe(collect([$admin->id, $superAdmin->id])->sort()->values()->all());
    foreach ($dispatches as $dispatch) {
        expect($dispatch->context['notification_format'])->toBe('filament')
            ->and($dispatch->context['subject'])->not->toStartWith('settlement.')
            ->and($dispatch->context['body'])->toContain($finding->public_id);
        app(DatabaseNotificationAdapter::class)->send($dispatch);
    }
    expect($admin->notifications()->where('data->format', 'filament')->count())->toBe(1)
        ->and($superAdmin->notifications()->where('data->format', 'filament')->count())->toBe(1);
    $data = $admin->notifications()->firstOrFail()->data;
    expect($data['actions'][0]['url'])->toContain($finding->public_id)
        ->and($data['status'])->toBe('danger');
});

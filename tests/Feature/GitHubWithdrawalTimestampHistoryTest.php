<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Application\Services\WalletQueryService;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use App\Modules\Settlement\Filament\Vendor\Pages\VendorWithdrawalsPage;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('preserves dirty request history and reserves approved funds once while displaying the viewer timezone', function (string $locale, string $timezone, int $amountMinor, int $availableMinor, string $localTime): void {
    $vendor = VendorProfile::factory()->approved()->create([
        'bank_account_holder' => 'Timestamp Test Vendor',
        'bank_iban' => 'EG380019000500000000263180002',
        'bank_name' => 'Test Bank',
        'bank_swift_bic' => 'TESTEGCX',
    ]);
    $vendorUser = $vendor->user;
    $vendorUser->update(['timezone' => $timezone]);

    $admin = User::factory()->superAdmin()->create(['timezone' => $timezone]);
    foreach (['view_any_withdrawals::queue', 'view_withdrawals::queue', 'withdrawal.view_audit'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $requestInstant = Carbon::parse('2026-05-22 16:49:00', 'UTC');
    $approvalInstant = Carbon::parse('2026-10-12 16:49:00', 'UTC');
    $idempotencyKey = 'withdrawal-timestamp-credit:'.Str::ulid();

    Carbon::setTestNow($requestInstant);
    app(LedgerWriter::class)->post(new PostLedgerTransactionInput(
        kind: TransactionKind::CommissionAccrual,
        currency: 'EGP',
        idempotencyKey: $idempotencyKey,
        correlationId: (string) Str::ulid(),
        causationId: null,
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformClearing->value,
                direction: LedgerDirection::Debit,
                amountMinor: 100_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: VendorProfile::class,
                counterAccountId: $vendor->id,
            ),
            new LedgerEntryInput(
                walletOwnerType: VendorProfile::class,
                walletOwnerId: $vendor->id,
                direction: LedgerDirection::Credit,
                amountMinor: 100_000,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformClearing->value,
            ),
        ],
    ));

    $withdrawal = app(RequestWithdrawalAction::class)->execute(new RequestWithdrawalDto(
        vendorProfileId: $vendor->id,
        requestedByUserId: $vendorUser->id,
        amountMinor: $amountMinor,
        currency: 'EGP',
        bankAccount: new BankAccountSnapshot(
            account_holder: 'Timestamp Test Vendor',
            iban: 'EG380019000500000000263180002',
            bank_name: 'Test Bank',
            swift_bic: 'TESTEGCX',
        ),
    ));
    $requestTime = $withdrawal->requested_at->copy();
    $incoming = $withdrawal;
    $incoming->requested_at = $approvalInstant;
    $ledgerCount = WalletLedgerEntry::query()->count();

    Carbon::setTestNow($approvalInstant);
    $withdrawal = app(ApproveWithdrawalAction::class)->execute($incoming, $admin)->fresh();
    Carbon::setTestNow($approvalInstant->copy()->addHours(25));
    $replayed = app(ApproveWithdrawalAction::class)->execute($incoming, $admin);
    Carbon::setTestNow();

    expect($withdrawal->requested_at->equalTo($requestTime))->toBeTrue()
        ->and($withdrawal->requested_at->toISOString())->toBe('2026-05-22T16:49:00.000000Z')
        ->and($withdrawal->approved_at->toISOString())->toBe('2026-10-12T16:49:00.000000Z')
        ->and($replayed->approved_at->toISOString())->toBe('2026-10-12T16:49:00.000000Z')
        ->and(WalletLedgerEntry::query()->count())->toBe($ledgerCount)
        ->and(app(WalletQueryService::class)->balance($vendor->id)['available_minor'])->toBe($availableMinor)
        ->and(app(WalletQueryService::class)->balance($vendor->id)['pending_withdrawal_minor'])->toBe($amountMinor)
        ->and(DB::table('audit_logs')->where('auditable_type', Withdrawal::class)
            ->where('auditable_id', $withdrawal->id)->where('action', 'withdrawal_approved')->count())->toBe(1);

    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendorUser, 'vendor');

    $vendorRows = Livewire::test(VendorWithdrawalsPage::class);
    $vendorRows->assertSeeText(Carbon::parse($requestTime)->setTimezone($timezone)->translatedFormat('d M Y H:i').' '.$timezone)
        ->assertSeeText(Carbon::parse($withdrawal->approved_at)->setTimezone($timezone)->translatedFormat('d M Y H:i').' '.$timezone)
        ->assertSeeText($localTime.' '.$timezone)
        ->assertDontSeeText('16:49')
        ->callAction('requestWithdrawal', data: ['amount' => '600'])
        ->assertHasActionErrors(['amount' => 'max']);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin, 'web')
        ->get('/admin/settlement-withdrawal-audit/'.$withdrawal->public_id.'?lang='.$locale)
        ->assertOk()
        ->assertSeeText(__('settlement.columns.requested_at', [], $locale))
        ->assertSeeText(__('settlement.fields.approved_at', [], $locale))
        ->assertSeeText($localTime.' '.$timezone)
        ->assertDontSeeText('16:49');
    $this->get('/admin/settlement-withdrawals?lang='.$locale)
        ->assertOk()
        ->assertSeeText($localTime.' '.$timezone)
        ->assertDontSeeText('16:49');
})->with([
    'English Cairo EGP 500' => ['en', 'Africa/Cairo', 50_000, 50_000, '19:49'],
    'Arabic Cairo EGP 650' => ['ar', 'Africa/Cairo', 65_000, 35_000, '19:49'],
    'English Dubai EGP 650' => ['en', 'Asia/Dubai', 65_000, 35_000, '20:49'],
    'Arabic Dubai EGP 500' => ['ar', 'Asia/Dubai', 50_000, 50_000, '20:49'],
]);

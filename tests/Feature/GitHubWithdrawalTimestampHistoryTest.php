<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use App\Modules\Settlement\Filament\Vendor\Pages\VendorWithdrawalsPage;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('preserves the request time and renders distinct approval times in the viewer timezone', function (string $locale): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $vendorUser = $vendor->user;
    $vendorUser->update(['timezone' => 'Africa/Cairo']);

    $admin = User::factory()->superAdmin()->create(['timezone' => 'Africa/Cairo']);
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
        amountMinor: 50_000,
        currency: 'EGP',
        bankAccount: new BankAccountSnapshot(
            account_holder: 'Timestamp Test Vendor',
            iban: 'EG380019000500000000263180002',
            bank_name: 'Test Bank',
            swift_bic: 'TESTEGCX',
        ),
    ));
    $requestTime = $withdrawal->requested_at->copy();

    Carbon::setTestNow($approvalInstant);
    $withdrawal = app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin)->fresh();
    Carbon::setTestNow();

    expect($withdrawal->requested_at->equalTo($requestTime))->toBeTrue()
        ->and($withdrawal->requested_at->toISOString())->toBe('2026-05-22T16:49:00.000000Z')
        ->and($withdrawal->approved_at->toISOString())->toBe('2026-10-12T16:49:00.000000Z');

    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendorUser, 'vendor');

    $vendorRows = Livewire::test(VendorWithdrawalsPage::class);
    $vendorRows->assertSeeText(Carbon::parse($requestTime)->setTimezone('Africa/Cairo')->translatedFormat('d M Y H:i'))
        ->assertSeeText(Carbon::parse($withdrawal->approved_at)->setTimezone('Africa/Cairo')->translatedFormat('d M Y H:i'))
        ->assertDontSeeText('16:49');

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin, 'web')
        ->get('/admin/settlement-withdrawal-audit/'.$withdrawal->public_id.'?lang='.$locale)
        ->assertOk()
        ->assertSeeText(__('settlement.columns.requested_at', [], $locale))
        ->assertSeeText(__('settlement.fields.approved_at', [], $locale))
        ->assertSeeText('19:49')
        ->assertDontSeeText('16:49');
})->with(['en', 'ar']);

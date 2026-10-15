<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDetailPage;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Application\Actions\ProcessRefundAction;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\CalculateCommissionAction;
use App\Modules\Settlement\Application\Actions\RejectWithdrawalAction;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PaymentSnapshotDto;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Application\Services\WalletQueryService;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use App\Modules\Settlement\Filament\Vendor\Pages\VendorStatementPage;
use App\Modules\Settlement\Filament\Vendor\Pages\VendorWalletPage;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

function financeVendorWithBankDetails(): VendorProfile
{
    return VendorProfile::factory()->approved()->create([
        'bank_account_holder' => 'Finance Test Vendor',
        'bank_iban' => 'EG380019000500000000263180002',
        'bank_name' => 'Test Bank',
        'bank_swift_bic' => 'TESTEGCX',
    ]);
}

function creditFinanceVendor(VendorProfile $vendor, int $amountMinor): void
{
    app(LedgerWriter::class)->post(new PostLedgerTransactionInput(
        kind: TransactionKind::CommissionAccrual,
        currency: 'EGP',
        idempotencyKey: 'github-finance-fixture:vendor-credit:'.$vendor->id,
        correlationId: (string) Str::ulid(),
        causationId: null,
        initiatorType: 'system',
        initiatedByUserId: null,
        entries: [
            new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformClearing->value,
                direction: LedgerDirection::Debit,
                amountMinor: $amountMinor,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: VendorProfile::class,
                counterAccountId: $vendor->id,
            ),
            new LedgerEntryInput(
                walletOwnerType: VendorProfile::class,
                walletOwnerId: $vendor->id,
                direction: LedgerDirection::Credit,
                amountMinor: $amountMinor,
                entryType: LedgerEntryType::CommissionAccrual,
                counterAccountType: 'platform_account',
                counterAccountId: SuspenseAccount::PlatformClearing->value,
            ),
        ],
    ));
}

function requestFinanceWithdrawal(VendorProfile $vendor, User $requester, int $amountMinor): Withdrawal
{
    return app(RequestWithdrawalAction::class)->execute(new RequestWithdrawalDto(
        vendorProfileId: $vendor->id,
        requestedByUserId: $requester->id,
        amountMinor: $amountMinor,
        currency: 'EGP',
        bankAccount: new BankAccountSnapshot(
            account_holder: 'Finance Test Vendor',
            iban: 'EG380019000500000000263180002',
            bank_name: 'Test Bank',
            swift_bic: 'TESTEGCX',
        ),
    ));
}

it('creates a valid vendor withdrawal request through the wallet page and reserves its exact minor units', function (string $amount, int $reserveMinor, int $remainingMinor): void {
    $vendor = financeVendorWithBankDetails();
    creditFinanceVendor($vendor, 163800);

    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(VendorWalletPage::class)
        ->callAction('requestWithdrawal', data: ['amount' => $amount])
        ->assertHasNoActionErrors();

    $wallet = Wallet::query()
        ->where('owner_type', VendorProfile::class)
        ->where('owner_id', $vendor->id)
        ->firstOrFail();

    expect(Withdrawal::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(1)
        ->and($wallet->balance_minor)->toBe($remainingMinor)
        ->and($wallet->pending_withdrawal_minor)->toBe($reserveMinor)
        ->and(WalletLedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->where('entry_type', LedgerEntryType::WithdrawalReserve)
            ->where('direction', LedgerDirection::Debit)
            ->sum('amount_minor'))->toBe($reserveMinor);
})->with([
    'reported minimum valid amount' => ['100', 10000, 153800],
    'reported second valid amount' => ['200', 20000, 143800],
    'larger valid amount' => ['500', 50000, 113800],
]);

it('keeps approved withdrawal funds excluded once by the ledger balance and allows a further request from the remainder', function (): void {
    $vendor = financeVendorWithBankDetails();
    creditFinanceVendor($vendor, 163800);
    $withdrawal = requestFinanceWithdrawal($vendor, $vendor->user, 100000);
    $admin = User::factory()->asAdmin()->create();

    app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);

    $wallet = Wallet::query()
        ->where('owner_type', VendorProfile::class)
        ->where('owner_id', $vendor->id)
        ->firstOrFail();

    expect($wallet->balance_minor)->toBe(63800)
        ->and($wallet->pending_withdrawal_minor)->toBe(100000)
        ->and(app(WalletQueryService::class)->balance($vendor->id)['available_minor'])->toBe(63800)
        ->and(DB::table('idempotency_keys')->where('key', 'wd_approve:'.$withdrawal->id)->where('scope_key', 'internal_action')->exists())->toBeTrue();

    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(VendorWalletPage::class)
        ->callAction('requestWithdrawal', data: ['amount' => '300'])
        ->assertHasNoActionErrors();

    expect(Withdrawal::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(2)
        ->and($wallet->fresh()->balance_minor)->toBe(33800)
        ->and(app(WalletQueryService::class)->balance($vendor->id)['available_minor'])->toBe(33800);
});

it('returns a rejected reservation to available balance and clears the pending projection', function (): void {
    $vendor = financeVendorWithBankDetails();
    creditFinanceVendor($vendor, 163800);
    $withdrawal = requestFinanceWithdrawal($vendor, $vendor->user, 50000);

    app(RejectWithdrawalAction::class)->execute($withdrawal, [
        'en' => 'Test rejection.',
        'ar' => 'رفض تجريبي.',
    ], User::factory()->asAdmin()->create());

    $wallet = Wallet::query()
        ->where('owner_type', VendorProfile::class)
        ->where('owner_id', $vendor->id)
        ->firstOrFail();

    expect($wallet->balance_minor)->toBe(163800)
        ->and($wallet->pending_withdrawal_minor)->toBe(0)
        ->and(app(WalletQueryService::class)->balance($vendor->id)['available_minor'])->toBe(163800);
});

it('calculates each statement balance from ledger directions when stored snapshots are stale', function (): void {
    $vendor = financeVendorWithBankDetails();
    $wallet = Wallet::factory()->create([
        'owner_type' => VendorProfile::class,
        'owner_id' => $vendor->id,
        'currency' => 'EGP',
    ]);
    $first = now()->subMinutes(3);
    $second = now()->subMinutes(2);
    $third = now()->subMinute();

    $firstCredit = WalletLedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'direction' => LedgerDirection::Credit,
        'amount_minor' => 73800,
        'running_balance_minor' => 73800,
        'created_at' => $first,
    ]);
    $secondCredit = WalletLedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'direction' => LedgerDirection::Credit,
        'amount_minor' => 90000,
        'running_balance_minor' => 90000,
        'created_at' => $second,
    ]);
    $withdrawalDebit = WalletLedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'direction' => LedgerDirection::Debit,
        'entry_type' => LedgerEntryType::WithdrawalDebit,
        'amount_minor' => 50000,
        'running_balance_minor' => 90000,
        'created_at' => $third,
    ]);

    $this->actingAs($vendor->user, 'vendor')
        ->get(VendorStatementPage::getUrl(panel: 'vendor'))
        ->assertOk();

    Livewire::test(VendorStatementPage::class)
        ->assertCanSeeTableRecords([$firstCredit, $secondCredit, $withdrawalDebit])
        ->assertSeeText('738.00')
        ->assertSeeText('1,638.00')
        ->assertSeeText('1,138.00');
});

it('persists and displays the total vendor payout from multiple calculated item shares plus delivery once', function (int $bps, array $expectedCommissions, int $expectedPayout, string $expectedDisplay): void {
    $vendor = financeVendorWithBankDetails();
    $booking = Booking::factory()->create(['subtotal_minor' => 20004, 'delivery_total_minor' => 7700, 'total_minor' => 27704]);
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'subtotal_minor' => 20004,
        'subtotal_currency' => 'EGP',
        'delivery_fee_minor' => 7700,
        'delivery_fee_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_currency' => 'EGP',
        'vendor_payout_minor' => 0,
        'vendor_payout_currency' => 'EGP',
    ]);
    BookingItem::factory()->sale()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'unit_price_minor' => 10001,
        'line_total_minor' => 10001,
        'line_total_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_bps' => $bps,
        'quantity' => 1,
    ]);
    BookingItem::factory()->sale()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'unit_price_minor' => 10003,
        'line_total_minor' => 10003,
        'line_total_currency' => 'EGP',
        'commission_minor' => 0,
        'commission_bps' => $bps,
        'quantity' => 1,
    ]);
    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'amount_minor' => 27704,
        'amount_currency' => 'EGP',
    ]);
    $paymentSnapshot = new PaymentSnapshotDto(
        id: $payment->id,
        publicId: $payment->public_id,
        bookingId: $payment->booking_id,
        amountMinor: $payment->amount_minor,
        currency: $payment->amount_currency,
        status: PaymentStatus::Captured,
        capturedAt: Carbon::parse($payment->captured_at),
    );

    $items = app(SettlementBookingReader::class)->itemsForPayment($booking->id);
    $calculator = app(CalculateCommissionAction::class);

    foreach ($items as $item) {
        $calculator->execute($item, $paymentSnapshot);
    }

    $bookingVendor->update(['vendor_payout_minor' => 0]);
    $bookingVendor->items()->update(['commission_minor' => 0]);
    $calculator->execute($items[0], $paymentSnapshot);
    $bookingVendor->refresh();

    expect(Commission::query()->whereIn('booking_item_id', array_column($items, 'id'))->count())->toBe(2)
        ->and(Commission::query()->whereIn('booking_item_id', array_column($items, 'id'))->orderBy('booking_item_id')->pluck('commission_minor')->all())->toBe($expectedCommissions)
        ->and($bookingVendor->vendor_payout_minor)->toBe($expectedPayout);
    expect(Wallet::query()->where('owner_type', VendorProfile::class)->where('owner_id', $vendor->id)->value('balance_minor'))
        ->toBe($expectedPayout);
    expect(DB::table('ledger_transaction_groups')->where('idempotency_key', 'vendor_delivery:'.$bookingVendor->id)->count())->toBe(1);

    $this->actingAs($vendor->user, 'vendor')
        ->get(VendorBookingDetailPage::getUrl(['bookingVendor' => $bookingVendor->public_id], panel: 'vendor'))
        ->assertOk()
        ->assertSeeText($expectedDisplay);
    $refund = Refund::factory()->create([
        'payment_id' => $payment->id, 'booking_id' => $booking->id, 'initiated_by' => $vendor->user_id,
        'amount_minor' => 27704, 'amount_currency' => 'EGP',
    ]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('refund')->once()->andReturn(new RefundResultDto(
        success: true, gatewayRef: 'qa-delivery-retained', failureMessage: null,
    ));
    app()->instance(PaymentGateway::class, $gateway);
    app(ProcessRefundAction::class)->execute($refund->id);
    app(ProcessRefundAction::class)->execute($refund->id);
    expect($refund->fresh()->amount_minor)->toBe(27704)
        ->and($refund->fresh()->status)->toBe(RefundStatus::Completed)
        ->and(Commission::query()->where('payment_id', $payment->id)->where('status', 'reversed')->count())->toBe(2)
        ->and(Wallet::query()->where('owner_type', VendorProfile::class)->where('owner_id', $vendor->id)->value('balance_minor'))->toBe(7700);
})->with([
    'half-even commission and one delivery fee' => [5000, [5000, 5002], 17702, '177.02'],
    'zero commission preserves the full vendor amount' => [0, [0, 0], 27704, '277.04'],
]);

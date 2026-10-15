<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\CalculateCommissionAction;
use App\Modules\Settlement\Application\Actions\MarkWithdrawalPaidAction;
use App\Modules\Settlement\Application\Actions\ProjectWalletBalanceAction;
use App\Modules\Settlement\Application\Actions\RejectWithdrawalAction;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\Actions\RunReconciliationAction;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\MarkWithdrawalPaidInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Application\DTOs\RunReconciliationInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Exceptions\InsufficientAvailableBalanceException;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentWalletRepository;
use Brick\Money\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function remainingSettlementVendor(): VendorProfile
{
    return VendorProfile::factory()->approved()->create([
        'bank_account_holder' => 'Local Settlement Fixture',
        'bank_iban' => 'EG380019000500000000263180002',
        'bank_name' => 'Fixture Bank',
        'bank_swift_bic' => 'TESTEGCX',
    ]);
}

function remainingSettlementWithdrawal(VendorProfile $vendor, User $requester, int $amountMinor): Withdrawal
{
    return app(RequestWithdrawalAction::class)->execute(new RequestWithdrawalDto(
        vendorProfileId: $vendor->id,
        requestedByUserId: $requester->id,
        amountMinor: $amountMinor,
        currency: 'EGP',
        bankAccount: new BankAccountSnapshot(
            account_holder: 'Local Settlement Fixture',
            iban: 'EG380019000500000000263180002',
            bank_name: 'Fixture Bank',
            swift_bic: 'TESTEGCX',
        ),
    ));
}

it('posts one balanced commission and vendor credit for a captured booking item, including the delivery credit', function (): void {
    $vendor = remainingSettlementVendor();
    $customer = User::factory()->asCustomer()->create();
    $admin = User::factory()->superAdmin()->create();
    $booking = Booking::factory()->confirmed()->completed()->create([
        'customer_id' => $customer->id,
        'subtotal_minor' => 100000,
        'delivery_total_minor' => 5000,
        'total_minor' => 105000,
        'amount_paid_minor' => 105000,
    ]);
    $allocation = BookingVendor::factory()->completed()->create([
        'booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id,
        'subtotal_minor' => 100000, 'delivery_fee_minor' => 5000,
    ]);
    $item = BookingItem::factory()->rental()->create([
        'booking_vendor_id' => $allocation->id, 'line_total_minor' => 100000,
        'product_type' => ProductType::Rental, 'item_status' => 'delivered', 'commission_bps' => 1500,
    ]);
    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id, 'amount_minor' => 105000, 'amount_currency' => 'EGP',
    ]);
    $reader = app(SettlementBookingReader::class);
    $itemSnapshot = $reader->findItemById($item->id);
    $paymentSnapshot = app(SettlementPaymentReader::class)->findById($payment->id);

    $commission = app(CalculateCommissionAction::class)->execute($itemSnapshot, $paymentSnapshot);
    $ledgerCount = WalletLedgerEntry::query()->count();
    $replay = app(CalculateCommissionAction::class)->execute($itemSnapshot, $paymentSnapshot);
    $wallet = Wallet::query()->where('owner_type', VendorProfile::class)->where('owner_id', $vendor->id)->firstOrFail();

    expect($replay->id)->toBe($commission->id)
        ->and($commission->gross_amount_minor)->toBe(100000)
        ->and($commission->commission_minor)->toBe(15000)
        ->and($commission->vendor_share_minor)->toBe(85000)
        ->and($allocation->fresh()->vendor_payout_minor)->toBe(90000)
        ->and($wallet->balance_minor)->toBe(90000)
        ->and(WalletLedgerEntry::query()->count())->toBe($ledgerCount)
        ->and(DB::table('ledger_transaction_groups')->where('idempotency_key', 'comm:'.$item->id)->count())->toBe(1)
        ->and(DB::table('wallet_ledger')->where('id', $commission->accrual_ledger_entry_id)->value('wallet_id'))->toBe($wallet->id)
        ->and((int) DB::table('wallet_ledger')->where('wallet_id', $wallet->id)->sum(DB::raw("CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END")))->toBe(90000);

    $this->actingAs($customer, 'web')
        ->get(route('storefront.account.bookings.invoice', ['locale' => 'en', 'bookingPublicId' => $booking->public_id]))
        ->assertOk()
        ->assertViewHas('booking', fn (Booking $invoiceBooking): bool => $invoiceBooking->total_minor === 105000)
        ->assertViewHas('vendorDeliveryMinor', 5000)
        ->assertViewHas('totalReconciliationMinor', 0)
        ->assertSeeText(Money::ofMinor(105000, 'EGP')->formatTo('en'));

    Sanctum::actingAs($vendor->user);
    $this->getJson('/api/v1/vendor/booking-vendors/'.$allocation->public_id)
        ->assertOk()
        ->assertJsonPath('data.subtotal_minor', 100000)
        ->assertJsonPath('data.delivery_fee_minor', 5000)
        ->assertJsonMissingPath('data.total_minor');

    $this->actingAs($admin, 'web')
        ->get(route('admin.bookings.invoice', [$booking->public_id, 'lang' => 'en']))
        ->assertOk()
        ->assertViewHas('booking', fn (Booking $invoiceBooking): bool => $invoiceBooking->total_minor === 105000)
        ->assertViewHas('vendorDeliveryMinor', 5000)
        ->assertViewHas('totalReconciliationMinor', 0)
        ->assertSeeText(Money::ofMinor(105000, 'EGP')->formatTo('en'));

    expect(Commission::query()->where('booking_item_id', $item->id)->sole()->vendor_share_minor)->toBe(85000)
        ->and($allocation->fresh()->vendor_payout_minor)->toBe(90000)
        ->and($wallet->fresh()->balance_minor)->toBe(90000);
});

it('reserves a vendor withdrawal once, approves it, and marks it paid with local fixture proof', function (): void {
    $vendor = remainingSettlementVendor();
    $requester = $vendor->user;
    $admin = User::factory()->superAdmin()->create();
    $booking = Booking::factory()->create(['subtotal_minor' => 15000, 'total_minor' => 15000]);
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'subtotal_minor' => 15000,
    ]);
    $item = BookingItem::factory()->rental()->create([
        'booking_vendor_id' => $bookingVendor->id, 'line_total_minor' => 15000, 'commission_bps' => 0,
    ]);
    $payment = Payment::factory()->captured()->create(['booking_id' => $bookingVendor->booking_id, 'amount_minor' => 15000]);
    $snapshot = app(SettlementBookingReader::class)->findItemById($item->id);
    $paymentSnapshot = app(SettlementPaymentReader::class)->findById($payment->id);
    app(CalculateCommissionAction::class)->execute($snapshot, $paymentSnapshot);

    $withdrawal = remainingSettlementWithdrawal($vendor, $requester, 12000);
    $reserveEntryId = $withdrawal->reserved_ledger_entry_id;
    $reserveCount = WalletLedgerEntry::query()->where('entry_type', LedgerEntryType::WithdrawalReserve)->count();
    app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
    app(ApproveWithdrawalAction::class)->execute($withdrawal, $admin);
    expect(Wallet::query()->where('owner_type', VendorProfile::class)->where('owner_id', $vendor->id)->value('pending_withdrawal_minor'))->toBe(12000);
    Storage::fake('s3-private');
    $proof = UploadedFile::fake()->createWithContent('fixture-proof.pdf', '%PDF-1.4 local nonpaying fixture');
    $paid = app(MarkWithdrawalPaidAction::class)->execute($withdrawal->fresh(), new MarkWithdrawalPaidInput(
        bankTransferReference: 'LOCAL-FIXTURE-'.$withdrawal->id,
        proofFile: $proof,
        paymentNote: ['en' => 'Non-paying local fixture', 'ar' => 'إثبات محلي دون دفع'],
    ), $admin);
    $wallet = Wallet::query()->where('owner_type', VendorProfile::class)->where('owner_id', $vendor->id)->firstOrFail();

    expect($paid->status->getValue())->toBe('paid')
        ->and($paid->reserved_ledger_entry_id)->toBe($reserveEntryId)
        ->and($paid->settled_ledger_entry_id)->not->toBeNull()
        ->and((int) $paid->paid_amount_minor)->toBe(12000)
        ->and($wallet->balance_minor)->toBe(3000)
        ->and($wallet->pending_withdrawal_minor)->toBe(0)
        ->and(WalletLedgerEntry::query()->where('entry_type', LedgerEntryType::WithdrawalReserve)->count())->toBe($reserveCount)
        ->and(DB::table('ledger_transaction_groups')->where('idempotency_key', 'wd_reserve:'.$withdrawal->id)->count())->toBe(1)
        ->and(DB::table('ledger_transaction_groups')->where('idempotency_key', 'wd_settle:'.$withdrawal->id)->count())->toBe(1);
});

it('keeps unmatched legacy reserves pending until an unmatched release entry nets them out', function (): void {
    $wallet = Wallet::factory()->empty()->create();
    WalletLedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'entry_type' => LedgerEntryType::WithdrawalReserve,
        'direction' => LedgerDirection::Debit,
        'amount_minor' => 18000,
        'related_entity_type' => 'withdrawal',
        'related_entity_id' => 999999,
    ]);

    app(ProjectWalletBalanceAction::class)->project($wallet->id);

    expect($wallet->fresh()->pending_withdrawal_minor)->toBe(18000);

    WalletLedgerEntry::factory()->create([
        'wallet_id' => $wallet->id,
        'entry_type' => LedgerEntryType::WithdrawalRejectRelease,
        'direction' => LedgerDirection::Credit,
        'amount_minor' => 18000,
        'related_entity_type' => 'withdrawal',
        'related_entity_id' => 999999,
    ]);
    app(ProjectWalletBalanceAction::class)->project($wallet->id);

    expect($wallet->fresh()->pending_withdrawal_minor)->toBe(0);
});

it('releases a rejected withdrawal once and refuses a request above the available ledger balance', function (): void {
    $vendor = remainingSettlementVendor();
    $admin = User::factory()->superAdmin()->create();
    app(EloquentWalletRepository::class)
        ->firstOrCreate(VendorProfile::class, $vendor->id);
    app(LedgerWriter::class)->post(new PostLedgerTransactionInput(
        kind: TransactionKind::CommissionAccrual,
        currency: 'EGP', idempotencyKey: 'remaining-settlement-credit:'.$vendor->id,
        correlationId: (string) Str::ulid(), causationId: null, initiatorType: 'system', initiatedByUserId: null,
        entries: [
            new LedgerEntryInput('platform_account', SuspenseAccount::PlatformClearing->value, LedgerDirection::Debit, 20000, LedgerEntryType::CommissionAccrual, VendorProfile::class, $vendor->id),
            new LedgerEntryInput(VendorProfile::class, $vendor->id, LedgerDirection::Credit, 20000, LedgerEntryType::CommissionAccrual, 'platform_account', SuspenseAccount::PlatformClearing->value),
        ],
    ));
    $withdrawal = remainingSettlementWithdrawal($vendor, $vendor->user, 10000);
    app(RejectWithdrawalAction::class)->execute($withdrawal, ['en' => 'Fixture rejection', 'ar' => 'رفض تجريبي'], $admin);
    $releaseCount = WalletLedgerEntry::query()->where('entry_type', LedgerEntryType::WithdrawalRejectRelease)->count();
    app(RejectWithdrawalAction::class)->execute($withdrawal, ['en' => 'Fixture rejection', 'ar' => 'رفض تجريبي'], $admin);
    $wallet = Wallet::query()->where('owner_type', VendorProfile::class)->where('owner_id', $vendor->id)->firstOrFail();

    expect($withdrawal->fresh()->status->getValue())->toBe('rejected')
        ->and($wallet->balance_minor)->toBe(20000)
        ->and($wallet->pending_withdrawal_minor)->toBe(0)
        ->and(WalletLedgerEntry::query()->where('entry_type', LedgerEntryType::WithdrawalRejectRelease)->count())->toBe($releaseCount);

    expect(fn () => remainingSettlementWithdrawal($vendor, $vendor->user, 30000))
        ->toThrow(InsufficientAvailableBalanceException::class);
    expect(Withdrawal::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(1);
});

it('completes wallet reconciliation with matching ledger projections and records one run per explicit replay key', function (): void {
    $vendor = remainingSettlementVendor();
    $booking = Booking::factory()->create(['subtotal_minor' => 25000, 'total_minor' => 25000]);
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'subtotal_minor' => 25000,
    ]);
    $item = BookingItem::factory()->rental()->create([
        'booking_vendor_id' => $bookingVendor->id, 'line_total_minor' => 25000, 'commission_bps' => 0,
    ]);
    $payment = Payment::factory()->captured()->create(['booking_id' => $booking->id, 'amount_minor' => 25000]);
    app(CalculateCommissionAction::class)->execute(
        app(SettlementBookingReader::class)->findItemById($item->id),
        app(SettlementPaymentReader::class)->findById($payment->id),
    );
    $wallet = Wallet::query()->where('owner_type', VendorProfile::class)->where('owner_id', $item->bookingVendor->vendor_profile_id)->firstOrFail();
    $wallet->update(['balance_minor' => 1]);
    $input = new RunReconciliationInput('wallet', ['wallet_id' => $wallet->id], 'manual', null, 'remaining-settlement-reconcile:'.$wallet->id, (string) Str::ulid());

    $result = app(RunReconciliationAction::class)->execute($input);
    $run = ReconciliationRun::findOrFail($result->runId);
    $replay = app(RunReconciliationAction::class)->execute(new RunReconciliationInput(
        'wallet', ['wallet_id' => $wallet->id], 'manual', null, 'remaining-settlement-recheck:'.$wallet->id, (string) Str::ulid(),
    ));

    expect($run->status)->toBe(ReconciliationStatus::Repaired)
        ->and($run->completed_at)->not->toBeNull()
        ->and($result->walletsScanned)->toBe(1)
        ->and($result->findingsCount)->toBe(1)
        ->and($replay->status)->toBe(ReconciliationStatus::Clean->value)
        ->and(DB::table('reconciliation_runs')->where('idempotency_key', $input->idempotencyKey)->count())->toBe(1)
        ->and(ReconciliationRun::query()->where('scope_type', 'wallet')->count())->toBe(2)
        ->and(ReconciliationFinding::query()->count())->toBe(1)
        ->and(DB::table('reconciliation_finding_runs')->where('reconciliation_finding_id', ReconciliationFinding::query()->value('id'))->count())->toBe(1)
        ->and(Wallet::findOrFail($wallet->id)->balance_minor)->toBe(25000);
});

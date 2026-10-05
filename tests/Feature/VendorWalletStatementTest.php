<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Database\Factories\WalletLedgerEntryFactory;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Filament\Vendor\Pages\VendorStatementPage;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

it('shows an owned wallet statement with a reconciled running balance', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $wallet = Wallet::factory()->create([
        'owner_type' => VendorProfile::class,
        'owner_id' => $vendor->id,
        'balance_minor' => 5000,
    ]);
    $credit = WalletLedgerEntryFactory::new()->commissionCredit()->create([
        'wallet_id' => $wallet->id,
        'amount_minor' => 5000,
        'running_balance_minor' => null,
        'created_at' => now()->subMinute(),
    ]);
    $debit = WalletLedgerEntryFactory::new()->withdrawalDebit()->create([
        'wallet_id' => $wallet->id,
        'amount_minor' => 1500,
        'running_balance_minor' => null,
        'created_at' => now(),
    ]);

    $this->actingAs($vendor->user, 'vendor')
        ->get(VendorStatementPage::getUrl(panel: 'vendor'))
        ->assertOk()
        ->assertSee(__('vendor-portal.wallet.statement_title'));

    Livewire::test(VendorStatementPage::class)
        ->assertCanSeeTableRecords([$credit, $debit]);
});

it('does not expose another vendor wallet statement', function (): void {
    $owner = VendorProfile::factory()->approved()->create();
    $foreign = VendorProfile::factory()->approved()->create();
    $wallet = Wallet::factory()->create([
        'owner_type' => VendorProfile::class,
        'owner_id' => $owner->id,
    ]);
    WalletLedgerEntryFactory::new()->commissionCredit()->create(['wallet_id' => $wallet->id]);

    $this->actingAs($foreign->user, 'vendor')
        ->get(VendorStatementPage::getUrl(panel: 'vendor'))
        ->assertOk();

    Livewire::test(VendorStatementPage::class)
        ->assertCanSeeTableRecords([])
        ->assertCountTableRecords(0);
});

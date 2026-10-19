<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Filament\Vendor\Pages\VendorStatementPage;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('includes signed legacy rows and reads cumulative balances without per-row aggregates', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $vendor = VendorProfile::factory()->approved()->create();
    $wallet = Wallet::factory()->create(['owner_type' => VendorProfile::class, 'owner_id' => $vendor->id]);
    WalletLedgerEntry::factory()->create(['wallet_id' => $wallet->id, 'amount_minor' => 10000, 'direction' => null, 'created_at' => now()->subMinutes(3)]);
    WalletLedgerEntry::factory()->create(['wallet_id' => $wallet->id, 'amount_minor' => 1000, 'direction' => LedgerDirection::Debit, 'created_at' => now()->subMinutes(2)]);
    WalletLedgerEntry::factory()->create(['wallet_id' => $wallet->id, 'amount_minor' => -2000, 'direction' => null, 'created_at' => now()->subMinute()]);
    $aggregates = 0;
    DB::listen(function (QueryExecuted $query) use (&$aggregates): void {
        if (str_contains(strtolower($query->sql), 'sum(') && str_contains($query->sql, 'wallet_ledger')) {
            $aggregates++;
        }
    });
    $this->actingAs($vendor->user, 'vendor');
    Livewire::test(VendorStatementPage::class)->assertSeeText('100.00')->assertSeeText('90.00')->assertSeeText('70.00');
    expect($aggregates)->toBe(0);
});

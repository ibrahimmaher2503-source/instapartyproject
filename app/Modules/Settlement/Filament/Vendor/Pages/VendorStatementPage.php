<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Vendor\Pages;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use Brick\Money\Money;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorStatementPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedVendor;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'finance';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'vendor-portal.pages.vendor-statement';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.wallet.statement_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.nav.items.statement');
    }

    public function table(Table $table): Table
    {
        $wallet = $this->getWallet();

        return $table
            ->heading(__('vendor-portal.wallet.statement_title'))
            ->paginated(false)
            ->description(__('vendor-portal.wallet.statement_order'))
            ->query($wallet === null
                ? WalletLedgerEntry::query()->whereRaw('1 = 0')
                : WalletLedgerEntry::query()
                    ->where('wallet_id', $wallet->id)
                    ->orderBy('created_at')
                    ->orderBy('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('vendor-portal.wallet.date'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('operation_reference')
                    ->label(__('vendor-portal.wallet.operation_reference'))
                    ->getStateUsing(fn (WalletLedgerEntry $record): string => $record->correlation_id ?: (string) $record->id)
                    ->copyable(),
                TextColumn::make('direction')
                    ->label(__('vendor-portal.wallet.credit_debit'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof LedgerDirection
                        ? __('vendor-portal.wallet.direction_'.$state->value)
                        : '—'),
                TextColumn::make('amount_minor')
                    ->label(__('vendor-portal.wallet.amount'))
                    ->getStateUsing(fn (WalletLedgerEntry $record): string => $this->formatMoney((int) $record->amount_minor, $record->currency)),
                TextColumn::make('running_balance')
                    ->label(__('vendor-portal.wallet.running_balance'))
                    ->getStateUsing(fn (WalletLedgerEntry $record): string => $this->formatMoney($this->runningBalance($record), $record->currency)),
            ]);
    }

    public function printButton(): string
    {
        return '<button type="button" onclick="window.print()">'.e(__('vendor-portal.wallet.print_statement')).'</button>';
    }

    private function getWallet(): ?Wallet
    {
        $vendor = auth()->user()->vendorProfile;

        return Wallet::query()
            ->where('owner_type', VendorProfile::class)
            ->where('owner_id', $vendor->id)
            ->where('currency', 'EGP')
            ->first();
    }

    /** @var array<int, array<int, int>> */
    private array $statementBalances = [];

    private function runningBalance(WalletLedgerEntry $record): int
    {
        if (! isset($this->statementBalances[$record->wallet_id])) {
            $balance = Money::ofMinor(0, $record->currency);
            $balances = [];
            foreach (WalletLedgerEntry::query()->where('wallet_id', $record->wallet_id)
                ->orderBy('created_at')->orderBy('id')->get() as $entry) {
                $amount = Money::ofMinor((int) $entry->amount_minor, $entry->currency);
                // Null directions identify historical signed entries.
                $balance = $entry->direction === LedgerDirection::Debit
                    ? $balance->minus($amount) : $balance->plus($amount);
                $balances[$entry->id] = $balance->getMinorAmount()->toInt();
            }
            $this->statementBalances[$record->wallet_id] = $balances;
        }

        return $this->statementBalances[$record->wallet_id][$record->id];
    }

    private function formatMoney(int $minor, string $currency): string
    {
        return Money::ofMinor($minor, $currency ?: 'EGP')->formatTo(app()->getLocale());
    }
}

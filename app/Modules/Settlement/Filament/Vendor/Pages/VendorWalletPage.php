<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Vendor\Pages;

use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Settlement\Application\Actions\RequestWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\RequestWithdrawalDto;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Exceptions\ExistingPendingWithdrawalException;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Domain\ValueObjects\BankAccountSnapshot;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Throwable;

class VendorWalletPage extends Page implements HasInfolists, HasTable
{
    use InteractsWithInfolists;
    use InteractsWithTable;
    use RequiresApprovedVendor;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'finance';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'vendor-portal.pages.vendor-wallet';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.wallet.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.wallet.title');
    }

    public function walletInfolist(Infolist $schema): Infolist
    {
        $wallet = $this->getWallet();

        return $schema
            ->record($wallet ?? new Wallet)
            ->components([
                TextEntry::make('balance_minor')
                    ->label(__('vendor-portal.wallet.current_balance'))
                    ->state(fn () => $wallet?->balance_minor ?? 0)
                    ->money('EGP', divideBy: 100),
                TextEntry::make('available_balance')
                    ->label(__('vendor-portal.wallet.available_balance'))
                    ->state(fn () => max(0, ($wallet?->balance_minor ?? 0) - ($wallet?->pending_withdrawal_minor ?? 0)))
                    ->money('EGP', divideBy: 100),
                TextEntry::make('pending_withdrawal_minor')
                    ->label(__('vendor-portal.wallet.pending_balance'))
                    ->state(fn () => $wallet?->pending_withdrawal_minor ?? 0)
                    ->money('EGP', divideBy: 100)
                    ->hint(__('vendor-portal.wallet.pending_clearance_hint'))
                    ->hintIcon('heroicon-o-information-circle'),
            ]);
    }

    public function table(Table $table): Table
    {
        $wallet = $this->getWallet();

        return $table
            ->heading(__('vendor-portal.wallet.ledger'))
            ->query(
                $wallet !== null
                    ? WalletLedgerEntry::query()
                        ->where('wallet_id', $wallet->id)
                        ->with('commission')
                        ->orderByDesc('created_at')
                    : WalletLedgerEntry::query()->whereRaw('1 = 0')
            )
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading(__('vendor-portal.wallet.empty_heading'))
            ->emptyStateDescription(__('vendor-portal.wallet.empty_description'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('vendor-portal.wallet.date'))
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('direction')
                    ->label(__('vendor-portal.wallet.type'))
                    ->badge()
                    ->color(fn (mixed $state): string => match (true) {
                        $state === LedgerDirection::Credit => 'success',
                        $state === LedgerDirection::Debit => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (mixed $state): string => match (true) {
                        $state === LedgerDirection::Credit => __('vendor-portal.wallet.direction_credit'),
                        $state === LedgerDirection::Debit => __('vendor-portal.wallet.direction_debit'),
                        default => '—',
                    }),
                TextColumn::make('amount_minor')
                    ->label(__('vendor-portal.wallet.amount'))
                    ->money('EGP', divideBy: 100)
                    ->color(fn (WalletLedgerEntry $record): string => $record->direction === LedgerDirection::Credit ? 'success' : 'danger'),
                TextColumn::make('running_balance_minor')
                    ->label(__('vendor-portal.wallet.running_balance'))
                    ->getStateUsing(fn (WalletLedgerEntry $record): string => $record->running_balance_minor !== null
                        ? sprintf('EGP %.2f', $record->running_balance_minor / 100)
                        : __('vendor-portal.wallet.running_balance_unavailable')
                    )
                    ->tooltip(fn (WalletLedgerEntry $record): ?string => $record->running_balance_minor === null
                        ? __('vendor-portal.wallet.running_balance_tooltip')
                        : null
                    ),
                TextColumn::make('entry_type')
                    ->label(__('vendor-portal.wallet.description'))
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof LedgerEntryType
                        ? ucwords(str_replace('_', ' ', $state->value))
                        : '—')
                    ->wrap(),
                TextColumn::make('commission_detail')
                    ->label(__('vendor-portal.wallet.commission_detail_label'))
                    ->getStateUsing(fn (WalletLedgerEntry $record): string => $record->commission !== null
                        ? __('vendor-portal.wallet.commission_detail', [
                            'rate' => number_format($record->commission->commission_bps / 100, 2),
                            'gross' => number_format($record->commission->gross_amount_minor / 100, 2),
                        ])
                        : '—'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->exports([
                        ExcelExport::make('ledger')
                            ->withFilename('wallet-ledger-'.date('Y-m-d'))
                            ->withColumns([
                                Column::make('created_at')->heading('Date')
                                    ->formatStateUsing(fn ($state) => $state?->format('d M Y H:i') ?? '—'),
                                Column::make('direction')->heading('Type')
                                    ->formatStateUsing(fn ($state) => $state?->value ?? '—'),
                                Column::make('amount_minor')->heading('Amount (EGP)')
                                    ->formatStateUsing(fn ($state) => number_format((int) $state / 100, 2)),
                                Column::make('running_balance_minor')->heading('Balance (EGP)')
                                    ->formatStateUsing(fn ($state) => number_format((int) $state / 100, 2)),
                                Column::make('entry_type')->heading('Description')
                                    ->formatStateUsing(fn ($state) => $state?->value ?? '—'),
                            ]),
                    ]),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->requestWithdrawalAction(),
        ];
    }

    public function requestWithdrawalAction(): Action
    {
        return Action::make('requestWithdrawal')
            ->label(__('vendor-portal.withdrawals.request'))
            ->icon('heroicon-o-arrow-up-right')
            ->color('primary')
            ->disabled(function (): bool {
                $wallet = $this->getWallet();
                $vendor = $this->getVendorProfile();
                $available = max(0, ($wallet?->balance_minor ?? 0) - ($wallet?->pending_withdrawal_minor ?? 0));

                return $available < 10000 || empty($vendor->bank_iban);
            })
            ->tooltip(function (): ?string {
                $wallet = $this->getWallet();
                $vendor = $this->getVendorProfile();
                $available = max(0, ($wallet?->balance_minor ?? 0) - ($wallet?->pending_withdrawal_minor ?? 0));
                if (empty($vendor->bank_iban)) {
                    return __('vendor-portal.profile.no_bank_details');
                }

                return $available < 10000
                    ? __('vendor-portal.withdrawals.insufficient_balance_tooltip')
                    : null;
            })
            ->form([
                Placeholder::make('available_balance_display')
                    ->label(__('vendor-portal.wallet.available_balance'))
                    ->content(function () {
                        $wallet = $this->getWallet();
                        $available = max(0, ($wallet?->balance_minor ?? 0) - ($wallet?->pending_withdrawal_minor ?? 0));

                        return sprintf('EGP %.2f', $available / 100);
                    }),
                TextInput::make('amount')
                    ->label(__('vendor-portal.withdrawals.amount').' (EGP)')
                    ->numeric()
                    ->required()
                    ->minValue(100)
                    ->helperText(__('vendor-portal.withdrawals.min_amount_hint'))
                    ->maxValue(function () {
                        $wallet = $this->getWallet();

                        return max(0, ($wallet?->balance_minor ?? 0) - ($wallet?->pending_withdrawal_minor ?? 0)) / 100;
                    }),
                Placeholder::make('fee_disclosure')
                    ->label(__('vendor-portal.withdrawals.fee_label'))
                    ->content(__('vendor-portal.withdrawals.fee_disclosure')),
                Placeholder::make('bank_details_display')
                    ->label(__('vendor-portal.withdrawals.bank_details'))
                    ->content(function () {
                        $vendor = $this->getVendorProfile();
                        if (! $vendor->bank_iban) {
                            return __('vendor-portal.profile.no_bank_details');
                        }

                        return sprintf(
                            '%s · %s · %s',
                            $vendor->bank_name ?? '—',
                            $vendor->bank_account_holder ?? '—',
                            $vendor->bank_iban ?? '—',
                        );
                    }),
            ])
            ->action(function (array $data): void {
                try {
                    $vendor = $this->getVendorProfile();

                    if (empty($vendor->bank_iban)) {
                        Notification::make()
                            ->title(__('vendor-portal.profile.no_bank_details'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $dto = new RequestWithdrawalDto(
                        vendorProfileId: $vendor->id,
                        requestedByUserId: auth()->id(),
                        amountMinor: (int) round((float) $data['amount'] * 100),
                        currency: 'EGP',
                        bankAccount: BankAccountSnapshot::fromArray([
                            'account_holder' => $vendor->bank_account_holder ?? '',
                            'iban' => $vendor->bank_iban ?? '',
                            'bank_name' => $vendor->bank_name ?? '',
                            'swift_bic' => $vendor->bank_swift_bic ?? '',
                        ]),
                    );
                    app(RequestWithdrawalAction::class)->execute($dto);
                    Notification::make()->title(__('vendor-portal.withdrawals.requested'))->success()->send();
                } catch (ExistingPendingWithdrawalException $e) {
                    Notification::make()
                        ->title(__('vendor-portal.withdrawals.cannot_have_two'))
                        ->warning()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title(__('vendor-portal.withdrawals.request_failed'))
                        ->body(__('vendor-portal.withdrawals.try_again_later'))
                        ->danger()
                        ->send();
                }
            });
    }

    protected function getWallet(): ?Wallet
    {
        $vendor = $this->getVendorProfile();

        return Wallet::query()
            ->where('owner_type', VendorProfile::class)
            ->where('owner_id', $vendor->id)
            ->where('currency', 'EGP')
            ->first();
    }

    protected function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}

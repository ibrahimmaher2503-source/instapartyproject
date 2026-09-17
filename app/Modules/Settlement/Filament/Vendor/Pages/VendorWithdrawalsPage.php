<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Vendor\Pages;

use App\Modules\Settlement\Domain\Models\Withdrawal;
use Exception;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorWithdrawalsPage extends VendorWalletPage
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'finance';

    protected static ?int $navigationSort = 2;

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.withdrawals.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.withdrawals.title');
    }

    public function table(Table $table): Table
    {
        $vendor = $this->getVendorProfile();

        return $table
            ->heading(__('vendor-portal.withdrawals.title'))
            ->query(
                Withdrawal::query()
                    ->where('vendor_profile_id', $vendor->id)
                    ->orderByDesc('requested_at')
            )
            ->emptyStateIcon('heroicon-o-arrow-up-tray')
            ->emptyStateHeading(__('vendor-portal.withdrawals.empty_heading'))
            ->emptyStateDescription(__('vendor-portal.withdrawals.empty_description'))
            ->columns([
                TextColumn::make('id')
                    ->label('')
                    ->formatStateUsing(fn (int $state): string => __('vendor-portal.withdrawals.label_format', ['id' => $state]))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('requested_at')
                    ->label(__('vendor-portal.withdrawals.requested_at'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('public_id')
                    ->label(__('vendor-portal.withdrawals.reference'))
                    ->copyable()
                    ->formatStateUsing(fn (string $state): string => strtoupper(substr($state, 0, 12)))
                    ->fontFamily('mono')
                    ->color('gray'),
                TextColumn::make('status')
                    ->label(__('vendor-portal.withdrawals.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'paid' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('requested_amount_minor')
                    ->label(__('vendor-portal.withdrawals.amount'))
                    ->money('EGP', divideBy: 100),
                Stack::make([
                    TextColumn::make('approved_at')
                        ->label(__('vendor-portal.withdrawals.approved_at'))
                        ->dateTime('d M Y H:i')
                        ->icon('heroicon-m-check-badge')
                        ->placeholder('—'),
                    TextColumn::make('paid_at')
                        ->label(__('vendor-portal.withdrawals.paid_at'))
                        ->dateTime('d M Y H:i')
                        ->icon('heroicon-m-banknotes')
                        ->placeholder('—'),
                ]),
                TextColumn::make('bank_transfer_reference')
                    ->label(__('vendor-portal.withdrawals.bank_reference'))
                    ->placeholder('—'),
            ])
            ->actions([
                TableAction::make('downloadProof')
                    ->label(__('vendor-portal.withdrawals.download_proof'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(function (Withdrawal $record): ?string {
                        $media = $record->getFirstMedia('bank_proof');
                        if ($media === null) {
                            return null;
                        }
                        try {
                            return $media->getTemporaryUrl(now()->addMinutes(15));
                        } catch (Exception) {
                            return null;
                        }
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (Withdrawal $record): bool => $record->getRawOriginal('status') === 'paid'
                        && $record->getFirstMedia('bank_proof') !== null
                    ),
            ]);
    }
}

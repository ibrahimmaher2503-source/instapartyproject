<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Application\Actions\ApproveWithdrawalAction;
use App\Modules\Settlement\Application\Actions\MarkWithdrawalPaidAction;
use App\Modules\Settlement\Application\Actions\RejectWithdrawalAction;
use App\Modules\Settlement\Application\DTOs\MarkWithdrawalPaidInput;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource\Pages\ListWithdrawalsQueue;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\ModelStates\State;
use Throwable;

class WithdrawalsQueueResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static ?string $slug = 'settlement-withdrawals';

    protected static ?int $navigationSort = 120;

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.withdrawals_queue');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.withdrawal.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.withdrawal.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('status', [WithdrawalStatus::Pending->value, WithdrawalStatus::Approved->value]);
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            Section::make(__('settlement.withdrawal.section_request'))
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('public_id')
                            ->label(__('settlement.columns.reference'))
                            ->copyable(),

                        TextEntry::make('status')
                            ->label(__('settlement.columns.status'))
                            ->badge()
                            ->color(fn (mixed $state): string => match (
                                $state instanceof State
                                    ? $state::getMorphClass()
                                    : (string) $state
                            ) {
                                'pending' => 'warning',
                                'approved' => 'info',
                                'paid' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (mixed $state): string => ucfirst(
                                $state instanceof State
                                    ? $state::getMorphClass()
                                    : (string) $state
                            )),

                        TextEntry::make('requested_amount_minor')
                            ->label(__('settlement.columns.amount'))
                            ->money('EGP', divideBy: 100),

                        TextEntry::make('requested_at')
                            ->label(__('settlement.columns.requested_at'))
                            ->dateTime(),

                        TextEntry::make('vendorProfile.business_name')
                            ->label(__('settlement.columns.vendor'))
                            ->default('—'),
                    ]),
                ]),

            Section::make(__('settlement.withdrawal.section_bank'))
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('bank_account_holder')
                            ->label(__('settlement.fields.account_holder'))
                            ->state(fn (Withdrawal $record): string => self::maskSensitiveLabel($record->bank_account_snapshot?->account_holder)),

                        TextEntry::make('bank_name')
                            ->label(__('settlement.fields.bank_name'))
                            ->state(fn (Withdrawal $record): string => self::maskSensitiveLabel($record->bank_account_snapshot?->bank_name)),

                        TextEntry::make('bank_iban')
                            ->label(__('settlement.fields.iban'))
                            ->state(fn (Withdrawal $record): string => self::maskIban($record->bank_account_snapshot?->iban)),

                        TextEntry::make('bank_swift_bic')
                            ->label(__('settlement.fields.swift_bic'))
                            ->state(fn (Withdrawal $record): string => self::maskSensitiveLabel($record->bank_account_snapshot?->swift_bic)),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('settlement.columns.reference'))
                    ->searchable()
                    ->copyable()
                    ->limit(14)
                    ->tooltip(fn (Withdrawal $record): string => $record->public_id),

                TextColumn::make('vendorProfile.business_name')
                    ->label(__('settlement.columns.vendor'))
                    ->searchable()
                    ->default('—'),

                TextColumn::make('requested_amount_minor')
                    ->money('EGP', divideBy: 100, locale: app()->getLocale())
                    ->label(__('settlement.columns.amount'))
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(function (mixed $state): string {
                        $value = $state instanceof State ? $state::getMorphClass() : (string) $state;

                        return match ($value) {
                            'pending' => 'warning',
                            'approved' => 'info',
                            'paid' => 'success',
                            'rejected' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function (mixed $state): string {
                        $value = $state instanceof State ? $state::getMorphClass() : (string) $state;

                        return WithdrawalStatus::tryFrom($value)?->label() ?? $value;
                    }),

                TextColumn::make('requested_at')
                    ->dateTime()
                    ->label(__('settlement.columns.requested_at'))
                    ->sortable(),

                TextColumn::make('approved_at')
                    ->dateTime()
                    ->label(__('settlement.columns.approved_at'))
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        WithdrawalStatus::Pending->value => __('settlement.withdrawal_status.pending'),
                        WithdrawalStatus::Approved->value => __('settlement.withdrawal_status.approved'),
                        WithdrawalStatus::Paid->value => __('settlement.withdrawal_status.paid'),
                        WithdrawalStatus::Rejected->value => __('settlement.withdrawal_status.rejected'),
                    ])
                    ->label(__('settlement.columns.status')),
            ])
            ->actions([
                ViewAction::make(),
                // ─── Step 1: Approve (only on Pending withdrawals) ─────────────────
                Action::make('approve')
                    ->label(__('settlement.actions.approve'))
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(__('settlement.actions.approve'))
                    ->modalDescription(__('settlement.actions.approve_description'))
                    ->action(function (Withdrawal $record): void {
                        try {
                            app(ApproveWithdrawalAction::class)->execute($record, auth()->user());

                            $vendorName = $record->vendorProfile?->business_name;
                            $vendorName = is_array($vendorName)
                                ? ($vendorName[app()->getLocale()] ?? $vendorName['en'] ?? '—')
                                : ($vendorName ?? '—');

                            Notification::make()
                                ->title(__('settlement.notifications.withdrawal_approved'))
                                ->body(sprintf('EGP %.2f — %s', $record->requested_amount_minor / 100, $vendorName))
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title(__('settlement.errors.withdrawal_state'))
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (Withdrawal $record): bool => $record->getRawOriginal('status') === WithdrawalStatus::Pending->value
                        && auth()->user()?->can('withdrawal.approve')
                    ),

                // ─── Step 2: Mark Paid (only on Approved withdrawals) ───────────────
                Action::make('markPaid')
                    ->label(__('settlement.actions.mark_paid'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        TextInput::make('bank_transfer_reference')
                            ->label(__('settlement.fields.bank_transfer_reference'))
                            ->required()
                            ->maxLength(120),

                        FileUpload::make('proof_file')
                            ->label(__('settlement.fields.transfer_proof'))
                            ->required()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(10240)
                            ->disk('local')
                            ->directory('withdrawal-proofs'),

                        Tabs::make('payment_note')
                            ->tabs([
                                Tab::make('English')
                                    ->schema([
                                        Textarea::make('admin_payment_note_en')
                                            ->label(__('settlement.fields.payment_note_en'))
                                            ->maxLength(1000)
                                            ->rows(3),
                                    ]),
                                Tab::make('العربية')
                                    ->schema([
                                        Textarea::make('admin_payment_note_ar')
                                            ->label(__('settlement.fields.payment_note_ar'))
                                            ->maxLength(1000)
                                            ->rows(3),
                                    ]),
                            ]),
                    ])
                    ->action(function (Withdrawal $record, array $data): void {
                        $path = $data['proof_file'];
                        $storagePath = Storage::disk('local')->path($path);
                        $uploadedFile = new UploadedFile(
                            path: $storagePath,
                            originalName: basename($storagePath),
                            mimeType: mime_content_type($storagePath) ?: 'application/octet-stream',
                            error: null,
                            test: false,
                        );

                        $paymentNote = array_filter([
                            'en' => $data['admin_payment_note_en'] ?? null,
                            'ar' => $data['admin_payment_note_ar'] ?? null,
                        ]);

                        try {
                            $input = new MarkWithdrawalPaidInput(
                                bankTransferReference: $data['bank_transfer_reference'],
                                proofFile: $uploadedFile,
                                paymentNote: $paymentNote,
                            );

                            app(MarkWithdrawalPaidAction::class)->execute($record, $input, auth()->user());

                            Notification::make()
                                ->title(__('settlement.notifications.withdrawal_paid'))
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title(__('settlement.errors.mark_paid_failed'))
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (Withdrawal $record): bool => $record->getRawOriginal('status') === WithdrawalStatus::Approved->value
                        && auth()->user()?->can('withdrawal.mark_paid')
                    ),

                ActionGroup::make([
                    Action::make('reject')
                        ->label(__('settlement.withdrawal.reject_action'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('rejected_reason_en')
                                ->label(__('settlement.withdrawal.rejection_reason_en'))
                                ->required()
                                ->rows(3),

                            Textarea::make('rejected_reason_ar')
                                ->label(__('settlement.withdrawal.rejection_reason_ar'))
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (Withdrawal $record, array $data): void {
                            app(RejectWithdrawalAction::class)->execute(
                                withdrawal: $record,
                                rejectedReason: [
                                    'en' => $data['rejected_reason_en'],
                                    'ar' => $data['rejected_reason_ar'],
                                ],
                                admin: auth()->user(),
                            );

                            Notification::make()
                                ->title(__('settlement.withdrawal.rejected_notification'))
                                ->warning()
                                ->send();
                        })
                        ->visible(fn (Withdrawal $record): bool => $record->getRawOriginal('status') === WithdrawalStatus::Pending->value
                            && auth()->user()?->can('reject_withdrawal')
                        ),
                ])
                    ->tooltip(__('admin.actions.more')),
            ])
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading(__('settlement.withdrawal.queue_empty'))
            ->emptyStateDescription(__('settlement.withdrawal.queue_empty_description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawalsQueue::route('/'),
        ];
    }

    private static function maskSensitiveLabel(?string $value): string
    {
        return $value === null || $value === '' ? '—' : mb_substr($value, 0, 1).'***';
    }

    private static function maskIban(?string $value): string
    {
        $iban = preg_replace('/\s+/', '', (string) $value) ?? '';

        return strlen($iban) <= 7 ? ($iban === '' ? '—' : '***') : substr($iban, 0, 4).'••••'.substr($iban, -3);
    }
}

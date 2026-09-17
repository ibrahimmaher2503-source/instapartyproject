<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalResource\Pages\ListWithdrawals;
use App\Modules\Settlement\Filament\Resources\WithdrawalResource\Pages\ViewWithdrawal;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use Exception;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\ModelStates\State;

class WithdrawalResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static ?string $slug = 'settlement-withdrawal-audit';

    protected static ?int $navigationSort = 130;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return __('settlement.withdrawal.nav_audit');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.withdrawal.audit_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.withdrawal.audit_plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            // ── Section 1: Request ─────────────────────────────────────────────
            Section::make(__('settlement.withdrawal.section_request'))
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('public_id')
                                ->label(__('settlement.columns.reference'))
                                ->copyable(),

                            TextEntry::make('status')
                                ->label(__('settlement.columns.status'))
                                ->badge()
                                ->color(function (mixed $state): string {
                                    $value = $state instanceof State
                                        ? $state::getMorphClass()
                                        : (string) $state;

                                    return match ($value) {
                                        'pending' => 'warning',
                                        'approved' => 'info',
                                        'paid' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    };
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

                            TextEntry::make('requestedBy.name')
                                ->label(__('settlement.columns.requested_by'))
                                ->default('—'),
                        ]),
                ]),

            // ── Section 2: Approval ────────────────────────────────────────────
            Section::make(__('settlement.withdrawal.section_approval'))
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('approved_at')
                                ->label(__('settlement.fields.approved_at'))
                                ->dateTime()
                                ->placeholder(__('settlement.withdrawal.not_yet_approved')),

                            TextEntry::make('approvedByAdmin.name')
                                ->label(__('settlement.fields.approved_by'))
                                ->placeholder('—'),
                        ]),
                ]),

            // ── Section 3: Payment ──────────────────────────────────────────────
            Section::make(__('settlement.withdrawal.section_payment'))
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('paid_at')
                                ->label(__('settlement.columns.paid_at'))
                                ->dateTime()
                                ->placeholder(__('settlement.withdrawal.not_paid_yet')),

                            TextEntry::make('paidByAdmin.name')
                                ->label(__('settlement.fields.paid_by'))
                                ->placeholder('—'),

                            TextEntry::make('bank_transfer_reference')
                                ->label(__('settlement.fields.bank_transfer_reference'))
                                ->copyable()
                                ->placeholder('—'),

                            TextEntry::make('admin_payment_note_en')
                                ->label(__('settlement.withdrawal.payment_note_en'))
                                ->state(fn (Withdrawal $record): string => self::localizedPaymentNote($record->admin_payment_note, 'en'))
                                ->placeholder('—'),

                            TextEntry::make('admin_payment_note_ar')
                                ->label(__('settlement.withdrawal.payment_note_ar'))
                                ->state(fn (Withdrawal $record): string => self::localizedPaymentNote($record->admin_payment_note, 'ar'))
                                ->placeholder('—'),
                        ]),
                ])
                ->visible(fn (): bool => auth()->user()?->can('withdrawal.view_audit') ?? false),

            // ── Section 4: Proof ────────────────────────────────────────────────
            Section::make(__('settlement.withdrawal.section_proof'))
                ->schema([
                    TextEntry::make('bank_proof_file')
                        ->label(__('settlement.withdrawal.proof_file'))
                        ->state(function (Withdrawal $record): string {
                            $media = $record->getFirstMedia('bank_proof');
                            if ($media === null) {
                                return __('settlement.withdrawal.no_proof_attached');
                            }
                            try {
                                $url = $media->getTemporaryUrl(now()->addMinutes(15));

                                return __('settlement.withdrawal.proof_available', ['filename' => $media->file_name]);
                            } catch (Exception) {
                                return __('settlement.withdrawal.proof_attached', ['filename' => $media->file_name]);
                            }
                        })
                        ->copyable(fn (Withdrawal $record): bool => $record->getFirstMedia('bank_proof') !== null),
                ])
                ->visible(fn (): bool => auth()->user()?->can('withdrawal.view_audit') ?? false),

            // ── Section 5: Ledger Links ─────────────────────────────────────────
            Section::make(__('settlement.withdrawal.section_ledger'))
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('reserved_ledger_entry_id')
                                ->label(__('settlement.withdrawal.reserve_ledger_entry'))
                                ->placeholder('—'),

                            TextEntry::make('settled_ledger_entry_id')
                                ->label(__('settlement.withdrawal.settle_ledger_entry'))
                                ->placeholder('—'),

                            TextEntry::make('rejected_ledger_entry_id')
                                ->label(__('settlement.withdrawal.reject_ledger_entry'))
                                ->placeholder('—'),
                        ]),
                ]),
            AuditTimelineSection::make()
                ->audience(TimelineAudience::Admin)
                ->columnSpanFull(),
        ]);
    }

    /** @param array<string, mixed>|null $note */
    private static function localizedPaymentNote(?array $note, string $locale): string
    {
        $value = $note[$locale] ?? null;

        return is_string($value) && $value !== '' ? $value : '—';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                    ->label(__('settlement.columns.requested'))
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->dateTime()
                    ->label(__('settlement.columns.paid_at'))
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
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawals::route('/'),
            'view' => ViewWithdrawal::route('/{record}'),
        ];
    }
}

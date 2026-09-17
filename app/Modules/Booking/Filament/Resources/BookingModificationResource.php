<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Filament\Resources\BookingModificationResource\Pages\ListBookingModifications;
use App\Modules\Booking\Filament\Resources\BookingModificationResource\Pages\ViewBookingModification;
use App\Modules\Shared\Application\Services\StorefrontText;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use Brick\Money\Money;
use Carbon\CarbonInterval;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingModificationResource extends Resource
{
    protected static ?string $model = BookingModification::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.operations');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.modifications');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.modification.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.models.modification.plural');
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['bookingVendor.vendor', 'bookingVendor.booking', 'proposedBy']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('booking.columns.public_id'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('public_id', 'like', '%'.self::escapeLike($search).'%')),
                TextColumn::make('bookingVendor.booking.reference_no')
                    ->label(__('booking.columns.reference_no'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'bookingVendor.booking',
                        fn (Builder $booking): Builder => $booking
                            ->where('reference_no', 'like', '%'.self::escapeLike($search).'%'),
                    ))
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('bookingVendor.vendor.business_name')
                    ->label(__('booking.columns.booking_vendor'))
                    ->getStateUsing(fn (BookingModification $record): string => $record->bookingVendor?->vendor === null
                        ? '—'
                        : (app(StorefrontText::class)->translation($record->bookingVendor->vendor, 'business_name') ?: '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'bookingVendor.vendor',
                        fn ($q) => $q
                            ->where('business_name->en', 'LIKE', '%'.self::escapeLike($search).'%')
                            ->orWhere('business_name->ar', 'LIKE', '%'.self::escapeLike($search).'%')
                    )),
                TextColumn::make('proposedBy.name')
                    ->label(__('booking.columns.proposed_by'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'proposedBy',
                        fn (Builder $proposer): Builder => $proposer
                            ->where('name', 'like', '%'.self::escapeLike($search).'%'),
                    )),
                TextColumn::make('proposal_kind')
                    ->label(__('booking.columns.proposal_kind'))
                    ->badge()
                    ->formatStateUsing(fn (ModificationProposalKind $state): string => $state->getLabel()),
                TextColumn::make('status')
                    ->getStateUsing(fn (BookingModification $record): ModificationStatus => $record->status === ModificationStatus::Pending
                        && $record->expires_at?->lessThanOrEqualTo(now('UTC'))
                            ? ModificationStatus::Expired
                            : $record->status)
                    ->label(__('booking.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (ModificationStatus $state): string => $state->getLabel()),
                TextColumn::make('expires_at')
                    ->label(__('booking.columns.expires_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('urgency')
                    ->label(__('booking.columns.urgency'))
                    ->badge()
                    ->getStateUsing(fn (BookingModification $record): string => self::urgency($record))
                    ->color(fn (BookingModification $record): string => $record->status === ModificationStatus::Pending
                        && $record->expires_at?->lessThanOrEqualTo(now('UTC')) ? 'danger' : 'success'),
                TextColumn::make('change_summary')
                    ->label(__('booking.modification_actions.comparison'))
                    ->getStateUsing(fn (BookingModification $record): string => self::changeSummary($record))
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (BookingModification $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            Section::make(__('booking.modification_actions.details_section'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('status')
                            ->getStateUsing(fn (BookingModification $record): ModificationStatus => $record->status === ModificationStatus::Pending
                                && $record->expires_at?->lessThanOrEqualTo(now('UTC'))
                                    ? ModificationStatus::Expired
                                    : $record->status)
                            ->label(__('booking.columns.status'))
                            ->badge()
                            ->color(fn (ModificationStatus $state): string => match ($state) {
                                ModificationStatus::Draft => 'gray',
                                ModificationStatus::Pending => 'warning',
                                ModificationStatus::CustomerAccepted => 'success',
                                ModificationStatus::CustomerRejected => 'danger',
                                ModificationStatus::Withdrawn => 'gray',
                                ModificationStatus::Expired => 'danger',
                            })
                            ->formatStateUsing(fn (ModificationStatus $state): string => ucwords(str_replace('_', ' ', $state->value))),
                        TextEntry::make('proposedBy.name')
                            ->label(__('booking.modification_actions.proposed_by')),
                        TextEntry::make('expires_at')
                            ->label(__('booking.columns.expires_at'))
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
                    Grid::make(1)->schema([
                        TextEntry::make('vendor_explanation')
                            ->label(__('booking.modification_actions.vendor_explanation'))
                            ->getStateUsing(fn (BookingModification $record): string => is_array($record->vendor_explanation)
                                ? ($record->vendor_explanation[app()->getLocale()] ?? $record->vendor_explanation['en'] ?? '—')
                                : '—')
                            ->placeholder('—'),
                        TextEntry::make('rejection_reason')
                            ->label(__('booking.modification_actions.rejection_reason'))
                            ->getStateUsing(fn (BookingModification $record): string => is_array($record->rejection_reason)
                                ? ($record->rejection_reason[app()->getLocale()] ?? $record->rejection_reason['en'] ?? '—')
                                : '—')
                            ->placeholder('—'),
                    ]),
                ]),
            Section::make(__('booking.modification_actions.comparison'))
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('diff_before')
                            ->label(__('booking.modification_actions.before'))
                            ->getStateUsing(fn (BookingModification $record): string => self::diffSideSummary($record, 'before')),
                        TextEntry::make('diff_after')
                            ->label(__('booking.modification_actions.after'))
                            ->getStateUsing(fn (BookingModification $record): string => self::diffSideSummary($record, 'after')),
                    ]),
                    TextEntry::make('diff_items')
                        ->label(__('booking.modification_actions.items'))
                        ->getStateUsing(fn (BookingModification $record): string => self::diffItemsSummary($record))
                        ->columnSpanFull()
                        ->prose(),
                ]),
            AuditTimelineSection::make()
                ->audience(TimelineAudience::Admin)
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingModifications::route('/'),
            'view' => ViewBookingModification::route('/{record}'),
        ];
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private static function urgency(BookingModification $record): string
    {
        if ($record->status !== ModificationStatus::Pending || $record->expires_at === null) {
            return __('booking.urgency.on_time');
        }

        if ($record->expires_at->isFuture()) {
            return __('booking.urgency.on_time');
        }

        return __('booking.urgency.overdue').' · '.CarbonInterval::seconds(max(1, now('UTC')->diffInSeconds($record->expires_at)))
            ->cascade()
            ->locale(app()->getLocale())
            ->forHumans();
    }

    private static function changeSummary(BookingModification $record): string
    {
        $before = data_get($record->diff_snapshot, 'before', []);
        $after = data_get($record->diff_snapshot, 'after', []);
        $currency = (string) (data_get($before, 'currency') ?? 'EGP');

        return __('booking.modification_actions.change_summary', [
            'before' => Money::ofMinor((int) data_get($before, 'subtotal_minor', 0), $currency)->formatTo(app()->getLocale()),
            'after' => Money::ofMinor((int) data_get($after, 'subtotal_minor', 0), $currency)->formatTo(app()->getLocale()),
            'items' => count((array) data_get($after, 'items', [])),
        ]);
    }

    private static function diffSideSummary(BookingModification $record, string $side): string
    {
        $snapshot = data_get($record->diff_snapshot, $side, []);
        $currency = (string) (data_get($snapshot, 'currency') ?? 'EGP');

        return __('booking.modification_actions.side_summary', [
            'subtotal' => Money::ofMinor((int) data_get($snapshot, 'subtotal_minor', 0), $currency)->formatTo(app()->getLocale()),
            'items' => count((array) data_get($snapshot, 'items', [])),
        ]);
    }

    private static function diffItemsSummary(BookingModification $record): string
    {
        $items = (array) data_get($record->diff_snapshot, 'after.items', []);

        if ($items === []) {
            return '—';
        }

        return collect($items)->map(function (array $item): string {
            $price = Money::ofMinor((int) ($item['unit_price_minor'] ?? 0), (string) ($item['unit_price_currency'] ?? 'EGP'))
                ->formatTo(app()->getLocale());

            return ($item['public_id'] ?? '—').': '.$price.' × '.(int) ($item['quantity'] ?? 1);
        })->implode("\n");
    }
}

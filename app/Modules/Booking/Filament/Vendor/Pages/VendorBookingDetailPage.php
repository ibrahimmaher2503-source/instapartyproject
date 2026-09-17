<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Application\Actions\Fulfillment\MarkCompletedAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkInProgressAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkPreparingAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkReadyAction;
use App\Modules\Booking\Application\Actions\Fulfillment\ReportFulfillmentIssueAction;
use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentIssueDto;
use App\Modules\Booking\Domain\Enums\FulfillmentIssueReason;
use App\Modules\Booking\Domain\Enums\FulfillmentIssueStatus;
use App\Modules\Booking\Domain\Enums\FulfillmentLane;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingFulfillmentIssue;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action as InlineAction;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

class VendorBookingDetailPage extends Page implements HasInfolists
{
    use InteractsWithInfolists;
    use RequiresApprovedVendor;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'vendor-portal.pages.vendor-booking-detail';

    public string $bookingVendor;

    public string $bookingPublicId = '';

    private ?BookingVendor $resolvedBookingVendor = null;

    public function mount(): void
    {
        $bookingVendor = request()->query('bookingVendor');
        abort_if(! $bookingVendor, 404);

        $record = BookingVendor::query()
            ->where('public_id', $bookingVendor)
            ->with(['booking.customer', 'booking.address', 'items'])
            ->firstOrFail();

        abort_if($record->vendor_profile_id !== $this->getVendorProfile()->id, 403);

        $this->bookingVendor = $bookingVendor;
        $this->bookingPublicId = $record->booking->public_id;
        $this->resolvedBookingVendor = $record;
    }

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.bookings.detail_title');
    }

    public function infolist(Infolist $schema): Infolist
    {
        $record = $this->getRecord();

        return $schema
            ->record($record)
            ->components([
                Section::make(__('vendor-portal.bookings.booking_info'))
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('booking.reference_no')
                                ->label(__('vendor-portal.bookings.reference')),
                            TextEntry::make('booking.event_starts_at')
                                ->label(__('vendor-portal.bookings.event_date'))
                                ->dateTime('d M Y, H:i'),
                            TextEntry::make('booking.customer.name')
                                ->label(__('vendor-portal.bookings.customer')),
                        ]),
                        Grid::make(2)->schema([
                            TextEntry::make('sub_status')
                                ->label(__('vendor-portal.bookings.status'))
                                ->badge()
                                ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', (string) ($state?->value ?? $state))))
                                ->color(fn ($state): string => match ($state?->value ?? $state) {
                                    'accepted', 'in_progress', 'completed' => 'success',
                                    'pending', 'modified' => 'warning',
                                    default => 'gray',
                                }),
                            TextEntry::make('booking.address.address_line')
                                ->label(__('vendor-portal.bookings.address'))
                                ->default('—'),
                        ]),
                        Grid::make(1)->schema([
                            TextEntry::make('response_deadline')
                                ->label(__('vendor-portal.bookings.response_deadline'))
                                ->formatStateUsing(fn (mixed $state): string => DeadlineColumnFormatter::format(
                                    $state instanceof Carbon ? $state : ($state ? Carbon::parse($state) : null)
                                ))
                                ->visible(fn (BookingVendor $record): bool => $record->sub_status === VendorSubStatus::Pending),
                        ]),
                    ]),

                Section::make(__('vendor-portal.bookings.items'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('name_snapshot')
                                        ->label(__('vendor-portal.services.name'))
                                        ->getStateUsing(fn (BookingItem $record): string => is_array($record->name_snapshot)
                                                ? ($record->name_snapshot[app()->getLocale()] ?? $record->name_snapshot['en'] ?? '—')
                                                : '—'
                                        ),
                                    TextEntry::make('product_type')
                                        ->label(__('catalog.product_type'))
                                        ->badge()
                                        ->color(fn (ProductType $state) => match ($state) {
                                            ProductType::Rental => 'warning',
                                            ProductType::Sale => 'success',
                                            ProductType::Digital => 'info',
                                        }),
                                    TextEntry::make('quantity')
                                        ->label(__('vendor-portal.bookings.quantity')),
                                    TextEntry::make('line_total_minor')
                                        ->label(__('vendor-portal.bookings.line_total'))
                                        ->money('EGP', divideBy: 100),
                                ]),
                            ]),
                    ]),

                $this->fulfillmentSection(),

                Section::make(__('vendor-portal.bookings.financial_breakdown'))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label(__('vendor-portal.bookings.per_item_breakdown'))
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('name_snapshot')
                                        ->label(__('vendor-portal.services.name'))
                                        ->getStateUsing(fn (BookingItem $record): string => is_array($record->name_snapshot)
                                                ? ($record->name_snapshot[app()->getLocale()] ?? $record->name_snapshot['en'] ?? '—')
                                                : '—'
                                        ),
                                    TextEntry::make('line_total_minor')
                                        ->label(__('vendor-portal.bookings.gross'))
                                        ->money('EGP', divideBy: 100),
                                    TextEntry::make('commission_bps')
                                        ->label(__('vendor-portal.bookings.commission_rate'))
                                        ->formatStateUsing(fn (int $state) => number_format($state / 100, 1).'%'),
                                ]),
                                Grid::make(3)->schema([
                                    TextEntry::make('commission_minor')
                                        ->label(__('vendor-portal.bookings.commission_amount'))
                                        ->money('EGP', divideBy: 100)
                                        ->color('danger'),
                                    TextEntry::make('vat_rate_bps')
                                        ->label(__('vendor-portal.bookings.vat_rate'))
                                        ->formatStateUsing(fn (int $state) => number_format($state / 100, 1).'%')
                                        ->visible(fn (int $state): bool => $state > 0),
                                    TextEntry::make('vat_amount_minor')
                                        ->label(__('vendor-portal.bookings.vat_amount'))
                                        ->money('EGP', divideBy: 100)
                                        ->color('warning')
                                        ->visible(fn (int $state): bool => $state > 0),
                                ]),
                            ]),

                        Grid::make(2)->schema([
                            TextEntry::make('subtotal_minor')
                                ->label(__('vendor-portal.bookings.gross_total'))
                                ->money('EGP', divideBy: 100),
                            TextEntry::make('delivery_fee_minor')
                                ->label(__('vendor-portal.bookings.delivery_fee'))
                                ->money('EGP', divideBy: 100),
                            TextEntry::make('total_vat')
                                ->label(__('vendor-portal.bookings.total_vat'))
                                ->state(fn (BookingVendor $record): int => $record->items->sum('vat_amount_minor'))
                                ->money('EGP', divideBy: 100)
                                ->color('warning'),
                            TextEntry::make('commission_minor')
                                ->label(__('vendor-portal.bookings.total_commission'))
                                ->money('EGP', divideBy: 100)
                                ->color('danger'),
                            TextEntry::make('vendor_payout_minor')
                                ->label(__('vendor-portal.bookings.net_payout'))
                                ->money('EGP', divideBy: 100)
                                ->helperText(__('vendor-portal.bookings.net_payout_hint'))
                                ->color('success')
                                ->weight('bold')
                                ->columnSpan(2),
                        ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('decide')
                ->label(__('vendor-portal.decision.title'))
                ->icon('heroicon-o-scale')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->sub_status === VendorSubStatus::Pending)
                ->url(fn () => VendorBookingDecisionPage::getUrl(['bookingVendor' => $this->bookingVendor])),

            Action::make('viewPayments')
                ->label(__('vendor-portal.bookings.view_payments'))
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->url(fn () => VendorBookingPaymentsPage::getUrl(['bookingVendor' => $this->bookingVendor])),

            Action::make('back')
                ->label(__('vendor-portal.back'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => VendorIncomingBookingsPage::getUrl()),
        ];
    }

    private function getRecord(): BookingVendor
    {
        if ($this->resolvedBookingVendor === null) {
            $this->resolvedBookingVendor = BookingVendor::query()
                ->where('public_id', $this->bookingVendor)
                ->with(['booking.customer', 'booking.address', 'items'])
                ->firstOrFail();
        }

        return $this->resolvedBookingVendor;
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }

    private function fulfillmentSection(): Section
    {
        return Section::make(__('vendor-portal.fulfillment.section_title'))
            ->visible(fn (): bool => ! in_array(
                $this->getRecord()->booking?->lifecycle_status,
                ['cancelled', 'refunded'],
                strict: true,
            ))
            ->schema([
                RepeatableEntry::make('items')
                    ->label('')
                    ->schema([
                        Grid::make(1)->schema([
                            TextEntry::make('item_status')
                                ->label(__('vendor-portal.bookings.status'))
                                ->badge()
                                ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', (string) $state)))
                                ->color(fn ($state): string => match ((string) $state) {
                                    'completed', 'sent', 'setup_complete', 'out_for_delivery' => 'success',
                                    'pending', 'pending_delivery' => 'gray',
                                    'in_preparation', 'in_progress', 'delivered' => 'warning',
                                    'ready' => 'info',
                                    default => 'gray',
                                }),

                            TextEntry::make('item_status')
                                ->label('')
                                ->badge()
                                ->color('info')
                                ->formatStateUsing(fn ($state): string => __('vendor-portal.fulfillment.awaiting_redemption'))
                                ->visible(fn ($state): bool => $state === 'sent'),

                            TextEntry::make('id')
                                ->label('')
                                ->badge()
                                ->color('danger')
                                ->formatStateUsing(fn ($state): string => __('vendor-portal.fulfillment.issue_reported_badge'))
                                ->visible(fn ($state): bool => $this->itemHasOpenIssue($state)),

                            Actions::make([
                                InlineAction::make('markPreparing')
                                    ->label(__('vendor-portal.fulfillment.lanes.preparing'))
                                    ->color('warning')
                                    ->requiresConfirmation()
                                    ->modalHeading(__('vendor-portal.fulfillment.lanes.preparing'))
                                    ->visible(fn ($record): bool => $record instanceof BookingItem && $this->canRenderButton('preparing', $record))
                                    ->action(fn ($record): mixed => $this->callMarkLane(FulfillmentLane::Preparing, $record->id)),

                                InlineAction::make('markReady')
                                    ->label(__('vendor-portal.fulfillment.lanes.ready'))
                                    ->color('warning')
                                    ->requiresConfirmation()
                                    ->modalHeading(__('vendor-portal.fulfillment.lanes.ready'))
                                    ->visible(fn ($record): bool => $record instanceof BookingItem && $this->canRenderButton('ready', $record))
                                    ->action(fn ($record): mixed => $this->callMarkLane(FulfillmentLane::Ready, $record->id)),

                                InlineAction::make('markInProgress')
                                    ->label(__('vendor-portal.fulfillment.lanes.in_progress'))
                                    ->color('info')
                                    ->requiresConfirmation()
                                    ->modalHeading(__('vendor-portal.fulfillment.lanes.in_progress'))
                                    ->visible(fn ($record): bool => $record instanceof BookingItem && $this->canRenderButton('in_progress', $record))
                                    ->action(fn ($record): mixed => $this->callMarkLane(FulfillmentLane::InProgress, $record->id)),

                                InlineAction::make('markCompleted')
                                    ->label(__('vendor-portal.fulfillment.lanes.completed'))
                                    ->color('success')
                                    ->visible(fn ($record): bool => $record instanceof BookingItem && $this->canRenderButton('completed', $record))
                                    ->modalHeading(__('vendor-portal.fulfillment.modal.complete_title'))
                                    ->modalDescription(__('vendor-portal.fulfillment.modal.complete_description'))
                                    ->form([
                                        Textarea::make('completion_note')
                                            ->label(__('vendor-portal.fulfillment.modal.fields.completion_note'))
                                            ->maxLength(2000)
                                            ->rows(3),
                                        FileUpload::make('completion_photo')
                                            ->label(__('vendor-portal.fulfillment.modal.fields.completion_photo'))
                                            ->image()
                                            ->maxSize(10240)
                                            ->disk('s3')
                                            ->directory('completion-evidence'),
                                        DateTimePicker::make('completed_at')
                                            ->label(__('vendor-portal.fulfillment.modal.fields.completed_at'))
                                            ->maxDate(now()),
                                    ])
                                    ->action(function ($record, array $data): void {
                                        try {
                                            $item = $record instanceof BookingItem ? $record : BookingItem::findOrFail($record->id);
                                            $vendor = $this->getVendorProfile();
                                            $actor = auth()->user();
                                            $evidence = new FulfillmentEvidenceDto(
                                                completionNote: $data['completion_note'] ?? null,
                                                completionPhoto: $data['completion_photo'] ?? null,
                                                completedAt: isset($data['completed_at']) ? CarbonImmutable::parse($data['completed_at']) : null,
                                            );
                                            app(MarkCompletedAction::class)->execute($item, $vendor, $actor, $evidence);
                                            Notification::make()
                                                ->title(__('vendor-portal.fulfillment.success.completed'))
                                                ->success()
                                                ->send();
                                            $this->resolvedBookingVendor = null;
                                        } catch (Throwable $e) {
                                            Notification::make()->title($e->getMessage())->danger()->send();
                                        }
                                    }),

                                InlineAction::make('reportIssue')
                                    ->label(__('vendor-portal.fulfillment.lanes.report_issue'))
                                    ->color('danger')
                                    ->visible(fn ($record): bool => $record instanceof BookingItem && $this->canShowReportIssue($record))
                                    ->modalHeading(__('vendor-portal.fulfillment.modal.report_issue_title'))
                                    ->modalDescription(__('vendor-portal.fulfillment.modal.report_issue_description'))
                                    ->form([
                                        Select::make('reason_code')
                                            ->label(__('vendor-portal.fulfillment.modal.fields.reason_code'))
                                            ->options(collect(FulfillmentIssueReason::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()]))
                                            ->required(),
                                        Textarea::make('note')
                                            ->label(__('vendor-portal.fulfillment.modal.fields.note'))
                                            ->required()
                                            ->maxLength(2000)
                                            ->rows(4),
                                        FileUpload::make('evidence_photos')
                                            ->label(__('vendor-portal.fulfillment.modal.fields.evidence_photos'))
                                            ->image()
                                            ->multiple()
                                            ->maxFiles(5)
                                            ->maxSize(10240)
                                            ->disk('s3')
                                            ->directory('issue-evidence'),
                                    ])
                                    ->action(function ($record, array $data): void {
                                        try {
                                            $item = $record instanceof BookingItem ? $record : BookingItem::findOrFail($record->id);
                                            $vendor = $this->getVendorProfile();
                                            $actor = auth()->user();
                                            $dto = new FulfillmentIssueDto(
                                                reasonCode: FulfillmentIssueReason::from($data['reason_code']),
                                                note: $data['note'],
                                                evidencePhotos: $data['evidence_photos'] ?? [],
                                            );
                                            app(ReportFulfillmentIssueAction::class)->execute($item, $vendor, $actor, $dto);
                                            Notification::make()
                                                ->title(__('vendor-portal.fulfillment.success.report_issue'))
                                                ->success()
                                                ->send();
                                            $this->resolvedBookingVendor = null;
                                        } catch (Throwable $e) {
                                            Notification::make()->title($e->getMessage())->danger()->send();
                                        }
                                    }),
                            ]),
                        ]),
                    ]),
            ]);
    }

    private function canRenderButton(string $lane, BookingItem $item): bool
    {
        $rawStatus = $item->item_status;
        $status = is_object($rawStatus) && method_exists($rawStatus, 'getValue')
            ? $rawStatus->getValue()
            : (string) $rawStatus;

        $type = $item->product_type instanceof ProductType
            ? $item->product_type->value
            : (string) $item->product_type;

        return match ($lane) {
            'preparing' => ($type === 'rental' && $status === 'pending_delivery')
                          || ($type === 'sale' && $status === 'pending'),
            'ready' => ($type === 'rental' && $status === 'out_for_delivery')
                          || ($type === 'sale' && $status === 'in_preparation'),
            'in_progress' => ($type === 'rental' && $status === 'delivered')
                          || ($type === 'sale' && $status === 'ready'),
            'completed' => ($type === 'rental' && $status === 'setup_complete')
                          || ($type === 'sale' && $status === 'out_for_delivery')
                          || ($type === 'digital' && $status === 'pending'),
            default => false,
        };
    }

    private function canShowReportIssue(BookingItem $item): bool
    {
        return ! BookingFulfillmentIssue::query()
            ->where('booking_item_id', $item->id)
            ->where('status', FulfillmentIssueStatus::Open)
            ->exists();
    }

    private function itemHasOpenIssue(int|string|null $itemId): bool
    {
        if ($itemId === null) {
            return false;
        }

        return BookingFulfillmentIssue::query()
            ->where('booking_item_id', $itemId)
            ->where('status', FulfillmentIssueStatus::Open)
            ->exists();
    }

    private function callMarkLane(FulfillmentLane $lane, int $itemId): void
    {
        try {
            $item = BookingItem::findOrFail($itemId);
            $vendor = $this->getVendorProfile();
            $actor = auth()->user();

            match ($lane) {
                FulfillmentLane::Preparing => app(MarkPreparingAction::class)->execute($item, $vendor, $actor),
                FulfillmentLane::Ready => app(MarkReadyAction::class)->execute($item, $vendor, $actor),
                FulfillmentLane::InProgress => app(MarkInProgressAction::class)->execute($item, $vendor, $actor),
                default => null,
            };

            Notification::make()
                ->title(__('vendor-portal.fulfillment.success.'.$lane->value))
                ->success()
                ->send();

            $this->resolvedBookingVendor = null;
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}

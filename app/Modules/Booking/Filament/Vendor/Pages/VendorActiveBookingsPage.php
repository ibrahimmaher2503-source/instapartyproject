<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Application\Actions\Fulfillment\Internal\MarkRentalPickedUpAction;
use App\Modules\Booking\Application\Actions\Fulfillment\Internal\MarkRentalTeardownAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkCompletedAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkInProgressAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkPreparingAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkReadyAction;
use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\States\DigitalItemStatus\PendingState as DigitalPending;
use App\Modules\Booking\Domain\States\DigitalItemStatus\RedeemedState;
use App\Modules\Booking\Domain\States\DigitalItemStatus\SentState;
use App\Modules\Booking\Domain\States\RentalItemStatus\DeliveredState;
use App\Modules\Booking\Domain\States\RentalItemStatus\OutForDeliveryState as RentalOutForDelivery;
use App\Modules\Booking\Domain\States\RentalItemStatus\PendingDeliveryState;
use App\Modules\Booking\Domain\States\RentalItemStatus\PickedUpState;
use App\Modules\Booking\Domain\States\RentalItemStatus\SetupCompleteState;
use App\Modules\Booking\Domain\States\RentalItemStatus\TeardownState;
use App\Modules\Booking\Domain\States\SaleItemStatus\DeliveredState as SaleDelivered;
use App\Modules\Booking\Domain\States\SaleItemStatus\InPreparationState;
use App\Modules\Booking\Domain\States\SaleItemStatus\OutForDeliveryState as SaleOutForDelivery;
use App\Modules\Booking\Domain\States\SaleItemStatus\PendingState as SalePending;
use App\Modules\Booking\Domain\States\SaleItemStatus\ReadyState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class VendorActiveBookingsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedVendor;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'vendor-portal.pages.vendor-active-bookings';

    public function mount(): void
    {
        $this->redirect(VendorBookingsPage::getUrl(['statusFilter' => 'in_progress']));
    }

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.bookings.active_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.bookings.active_title');
    }

    public function table(Table $table): Table
    {
        $vendorProfile = $this->getVendorProfile();
        $terminalStates = [PickedUpState::$name, SaleDelivered::$name, RedeemedState::$name];

        return $table
            ->query(
                BookingItem::query()
                    ->whereHas('bookingVendor', fn ($q) => $q
                        ->where('vendor_profile_id', $vendorProfile->id)
                        ->where('sub_status', VendorSubStatus::Accepted)
                    )
                    ->whereNotIn('item_status', $terminalStates)
                    ->with(['bookingVendor.booking'])
            )
            ->columns([
                TextColumn::make('bookingVendor.booking.reference_no')
                    ->label(__('vendor-portal.bookings.reference'))
                    ->searchable(),
                TextColumn::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->badge()
                    ->color(fn (ProductType $state) => match ($state) {
                        ProductType::Rental => 'warning',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'info',
                    })
                    ->formatStateUsing(fn (ProductType $state) => ucfirst($state->value)),
                TextColumn::make('name_snapshot')
                    ->label(__('catalog.name'))
                    ->getStateUsing(fn (BookingItem $record) => $record->name_snapshot[app()->getLocale()] ?? $record->name_snapshot['en'] ?? '—')
                    ->limit(30),
                TextColumn::make('item_status')
                    ->label(__('booking.columns.item_status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', (string) ($state?->value ?? $state))))
                    ->color(fn ($state): string => match ((string) ($state?->value ?? $state)) {
                        'completed', 'sent', 'setup_complete', 'out_for_delivery', 'delivered' => 'success',
                        'in_preparation', 'in_progress', 'ready' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('bookingVendor.booking.event_starts_at')
                    ->label(__('vendor-portal.bookings.event_date'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->emptyStateIcon('heroicon-o-bolt')
            ->emptyStateHeading(__('vendor-portal.bookings.empty_active_heading'))
            ->emptyStateDescription(__('vendor-portal.bookings.empty_active_description'))
            ->actions([
                TableAction::make('view_details')
                    ->label(__('vendor-portal.bookings.view_details'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (BookingItem $record) => VendorBookingDetailPage::getUrl([
                        'bookingVendor' => $record->bookingVendor->public_id,
                    ])),

                $this->makeTransitionAction('mark_in_transit', PendingDeliveryState::$name, RentalOutForDelivery::class),
                $this->makeTransitionAction('mark_setup_started', RentalOutForDelivery::$name, DeliveredState::class),
                $this->makeTransitionAction('mark_active', DeliveredState::$name, SetupCompleteState::class),
                $this->makeTransitionAction('mark_teardown_started', SetupCompleteState::$name, TeardownState::class),
                $this->makeTransitionAction('mark_completed', TeardownState::$name, PickedUpState::class),
                $this->makeTransitionAction('mark_in_preparation', SalePending::$name, InPreparationState::class),
                $this->makeTransitionAction('mark_ready', InPreparationState::$name, ReadyState::class),
                $this->makeTransitionAction('mark_out_for_delivery', ReadyState::$name, SaleOutForDelivery::class),
                $this->makeTransitionAction('mark_delivered', SaleOutForDelivery::$name, SaleDelivered::class),
                $this->makeTransitionAction('mark_sent', DigitalPending::$name, SentState::class),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_mark_in_transit')
                        ->label(__('vendor-portal.fulfillment.bulk_mark_in_transit'))
                        ->icon('heroicon-o-truck')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading(__('vendor-portal.fulfillment.bulk_mark_in_transit_confirm'))
                        ->action(function (Collection $records): void {
                            $profile = $this->getVendorProfile();
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->item_status === PendingDeliveryState::$name) {
                                    app(MarkPreparingAction::class)->execute(
                                        $record,
                                        $profile,
                                        auth()->user(),
                                    );
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title(__('vendor-portal.fulfillment.bulk_mark_in_transit_done', ['count' => $count]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /** @param class-string $toStateClass */
    private function makeTransitionAction(string $labelKey, string $visibleWhenStatus, string $toStateClass): TableAction
    {
        return TableAction::make($labelKey)
            ->label(__('vendor-portal.fulfillment.'.$labelKey))
            ->icon('heroicon-o-check-circle')
            ->color('primary')
            ->visible(fn (BookingItem $record) => $record->item_status === $visibleWhenStatus)
            ->action(function (BookingItem $record) use ($toStateClass, $labelKey): void {
                $vendor = $this->getVendorProfile();
                $actor = auth()->user();
                $evidence = new FulfillmentEvidenceDto;

                match ($toStateClass) {
                    RentalOutForDelivery::class, InPreparationState::class => app(MarkPreparingAction::class)->execute($record, $vendor, $actor),
                    DeliveredState::class, ReadyState::class => app(MarkReadyAction::class)->execute($record, $vendor, $actor),
                    SetupCompleteState::class, SaleOutForDelivery::class => app(MarkInProgressAction::class)->execute($record, $vendor, $actor),
                    TeardownState::class => app(MarkRentalTeardownAction::class)->execute($record, $vendor, $actor),
                    PickedUpState::class => app(MarkRentalPickedUpAction::class)->execute($record, $vendor, $actor, $evidence),
                    SaleDelivered::class, SentState::class => app(MarkCompletedAction::class)->execute($record, $vendor, $actor, $evidence),
                    default => throw new \LogicException('Unsupported fulfillment transition'),
                };
                Notification::make()->title(__('vendor-portal.fulfillment.'.$labelKey))->success()->send();
            });
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}

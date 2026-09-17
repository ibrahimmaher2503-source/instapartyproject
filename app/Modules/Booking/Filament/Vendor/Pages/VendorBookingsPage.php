<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Helpers\DeadlineColumnFormatter;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorBookingsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedVendor;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'vendor-portal.pages.vendor-bookings';

    public string $statusFilter = 'pending';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.bookings.unified_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.bookings.unified_title');
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user?->vendorProfile) {
            return null;
        }

        $count = BookingVendor::query()
            ->where('vendor_profile_id', $user->vendorProfile->id)
            ->where('sub_status', VendorSubStatus::Pending)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function table(Table $table): Table
    {
        $vendorProfile = $this->getVendorProfile();
        $statusFilter = $this->statusFilter;

        $query = BookingVendor::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->when($statusFilter === 'pending', fn ($q) => $q->where('sub_status', VendorSubStatus::Pending))
            ->when($statusFilter === 'accepted', fn ($q) => $q->where('sub_status', VendorSubStatus::Accepted))
            ->when($statusFilter === 'in_progress', fn ($q) => $q->where('sub_status', VendorSubStatus::InProgress))
            ->when($statusFilter === 'completed', fn ($q) => $q->where('sub_status', VendorSubStatus::Completed))
            ->when($statusFilter === 'rejected', fn ($q) => $q->whereIn('sub_status', [
                VendorSubStatus::Rejected->value,
                VendorSubStatus::TimedOut->value,
            ]))
            ->with(['booking', 'booking.customer', 'items'])
            ->orderByRaw('ISNULL(response_deadline), response_deadline ASC');

        return $table
            ->query($query)
            ->emptyStateIcon('heroicon-o-queue-list')
            ->emptyStateHeading(__('vendor-portal.bookings.empty_heading'))
            ->emptyStateDescription(__('vendor-portal.bookings.empty_description'))
            ->emptyStateActions([
                TableAction::make('shareProfile')
                    ->label(__('vendor-portal.bookings.share_profile'))
                    ->icon('heroicon-o-share')
                    ->color('primary')
                    ->action(function (): void {
                        $this->dispatch('open-modal', id: 'share-profile');
                    }),
            ])
            ->columns([
                TextColumn::make('booking.reference_no')
                    ->label(__('vendor-portal.bookings.reference'))
                    ->searchable(),
                TextColumn::make('booking.customer.name')
                    ->label(__('vendor-portal.bookings.customer')),
                TextColumn::make('booking.event_starts_at')
                    ->label(__('vendor-portal.bookings.event_date'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('subtotal_minor')
                    ->label(__('vendor-portal.bookings.total'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('sub_status')
                    ->label(__('vendor-portal.bookings.status'))
                    ->badge()
                    ->color(fn (VendorSubStatus $state): string => match ($state) {
                        VendorSubStatus::Pending => 'warning',
                        VendorSubStatus::Accepted, VendorSubStatus::InProgress, VendorSubStatus::Completed => 'success',
                        VendorSubStatus::Rejected, VendorSubStatus::TimedOut => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (VendorSubStatus $state): string => ucwords(str_replace('_', ' ', $state->value))),
                TextColumn::make('response_deadline')
                    ->label(__('vendor-portal.bookings.response_deadline'))
                    ->formatStateUsing(fn (mixed $state): string => DeadlineColumnFormatter::format(
                        $state instanceof Carbon ? $state : ($state ? Carbon::parse($state) : null)
                    ))
                    ->color(fn (BookingVendor $record): string => DeadlineColumnFormatter::color(
                        $record->response_deadline instanceof Carbon
                            ? $record->response_deadline
                            : ($record->response_deadline ? Carbon::parse($record->response_deadline) : null)
                    ))
                    ->visible(fn (): bool => in_array($this->statusFilter, ['pending'], true))
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('decide')
                    ->label(__('vendor-portal.decision.title'))
                    ->icon('heroicon-o-scale')
                    ->color('primary')
                    ->visible(fn (BookingVendor $record): bool => $record->sub_status === VendorSubStatus::Pending)
                    ->url(fn (BookingVendor $record): string => VendorBookingDecisionPage::getUrl(['bookingVendor' => $record->public_id])),
                TableAction::make('view_details')
                    ->label(__('vendor-portal.bookings.view_details'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (BookingVendor $record): string => VendorBookingDetailPage::getUrl(['bookingVendor' => $record->public_id])),
            ]);
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}

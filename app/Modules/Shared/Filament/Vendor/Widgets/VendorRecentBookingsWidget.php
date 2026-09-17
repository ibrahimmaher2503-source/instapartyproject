<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Vendor\Widgets;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDetailPage;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class VendorRecentBookingsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public function getTableHeading(): ?string
    {
        return __('vendor-portal.dashboard.recent_bookings.heading');
    }

    public static function canView(): bool
    {
        $profile = auth()->user()?->vendorProfile;

        return $profile?->approval_status instanceof ApprovedState;
    }

    public function table(Table $table): Table
    {
        $vendorId = auth()->user()?->vendorProfile?->id ?? 0;

        // Resolve the 5 most-recent IDs first, then constrain by them. Filament
        // rebuilds the table query (dropping a bare ->limit()), so a hard
        // whereIn is the driver-agnostic way to cap the list at 5.
        $latestIds = BookingVendor::query()
            ->where('vendor_profile_id', $vendorId)
            ->latest()
            ->limit(5)
            ->pluck('id')
            ->all();

        return $table
            ->query(
                BookingVendor::query()
                    ->whereIn('id', $latestIds)
                    ->with('booking')
                    ->latest()
            )
            ->paginated(false)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('booking.reference_no')
                    ->label(__('vendor-portal.dashboard.recent_bookings.reference')),
                TextColumn::make('booking.event_starts_at')
                    ->label(__('vendor-portal.dashboard.recent_bookings.event_date'))
                    ->dateTime('d M Y H:i'),
                TextColumn::make('sub_status')
                    ->label(__('vendor-portal.dashboard.recent_bookings.status'))
                    ->badge()
                    ->color(fn (VendorSubStatus $state): string => match ($state) {
                        VendorSubStatus::Pending => 'warning',
                        VendorSubStatus::Accepted, VendorSubStatus::InProgress, VendorSubStatus::Completed => 'success',
                        VendorSubStatus::Rejected, VendorSubStatus::TimedOut => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (VendorSubStatus $state): string => ucwords(str_replace('_', ' ', $state->value))),
                TextColumn::make('subtotal_minor')
                    ->label(__('vendor-portal.dashboard.recent_bookings.total'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('created_at')
                    ->label(__('vendor-portal.dashboard.recent_bookings.created_at'))
                    ->since(),
            ])
            ->recordUrl(fn (BookingVendor $record): string => env('MINIMAL_FILAMENT_PANELS', false)
                ? '/vendor-portal'
                : VendorBookingDetailPage::getUrl(['bookingVendor' => $record->public_id]))
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading(__('vendor-portal.dashboard.recent_bookings.empty_heading'))
            ->emptyStateDescription(__('vendor-portal.dashboard.recent_bookings.empty_description'));
    }
}

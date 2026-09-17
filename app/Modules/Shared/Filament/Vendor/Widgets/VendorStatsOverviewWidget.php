<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Vendor\Widgets;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Helpers\DeadlineColumnFormatter;
use App\Modules\Booking\Filament\Vendor\Pages\VendorActiveBookingsPage;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingsPage;
use App\Modules\Booking\Filament\Vendor\Pages\VendorIncomingBookingsPage;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Filament\Vendor\Pages\VendorWalletPage;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class VendorStatsOverviewWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        $profile = auth()->user()?->vendorProfile;

        return $profile?->approval_status instanceof ApprovedState;
    }

    protected function getStats(): array
    {
        $vendorProfile = auth()->user()->vendorProfile;

        if ($vendorProfile === null) {
            return [];
        }

        $vendorId = $vendorProfile->id;

        $data = Cache::remember("vendor_dashboard_stats_{$vendorId}", 300, function () use ($vendorId): array {
            $now = now();
            $som = $now->copy()->startOfMonth();

            $bookingsThisMonth = BookingVendor::query()
                ->where('vendor_profile_id', $vendorId)
                ->whereNotIn('sub_status', [VendorSubStatus::Cancelled->value, VendorSubStatus::TimedOut->value])
                ->whereBetween('created_at', [$som, $now])
                ->count();

            $pendingServices = Service::query()
                ->where('vendor_profile_id', $vendorId)
                ->where('status', ServiceStatus::PendingReview)
                ->count();

            $serviceCountsByType = Service::query()
                ->where('vendor_profile_id', $vendorId)
                ->selectRaw('product_type, COUNT(*) as aggregate')
                ->groupBy('product_type')
                ->pluck('aggregate', 'product_type')
                ->all();

            $pendingBookings = BookingVendor::query()
                ->where('vendor_profile_id', $vendorId)
                ->where('sub_status', VendorSubStatus::Pending)
                ->count();

            $activeBookings = BookingVendor::query()
                ->where('vendor_profile_id', $vendorId)
                ->whereIn('sub_status', [VendorSubStatus::Accepted->value, VendorSubStatus::InProgress->value])
                ->count();

            $avgRating = ServiceReview::query()
                ->whereHas('service', fn ($q) => $q->where('vendor_profile_id', $vendorId))
                ->where('moderation_status', ModerationStatus::Approved)
                ->avg('rating');

            $wallet = Wallet::query()
                ->where('owner_type', VendorProfile::class)
                ->where('owner_id', $vendorId)
                ->where('currency', 'EGP')
                ->first();

            $balanceMinor = $wallet ? max(0, $wallet->balance_minor - $wallet->pending_withdrawal_minor) : 0;

            $revenueThisMonthMinor = BookingVendor::query()
                ->where('vendor_profile_id', $vendorId)
                ->whereIn('sub_status', [VendorSubStatus::Accepted->value, VendorSubStatus::InProgress->value, VendorSubStatus::Completed->value])
                ->whereBetween('created_at', [$som, $now])
                ->sum('vendor_payout_minor');

            $upcomingEvents = BookingVendor::query()
                ->where('vendor_profile_id', $vendorId)
                ->where('sub_status', VendorSubStatus::Accepted->value)
                ->whereHas('booking', fn ($q) => $q->whereBetween('event_starts_at', [$now, $now->copy()->addDays(7)]))
                ->count();

            $nextDeadline = BookingVendor::query()
                ->where('vendor_profile_id', $vendorId)
                ->where('sub_status', VendorSubStatus::Pending)
                ->whereNotNull('response_deadline')
                ->where('response_deadline', '>', $now)
                ->orderBy('response_deadline')
                ->value('response_deadline');

            return [
                'bookingsThisMonth' => $bookingsThisMonth,
                'revenueEgp' => number_format($revenueThisMonthMinor / 100, 0),
                'pendingServices' => $pendingServices,
                'serviceCountsByType' => collect(ProductType::cases())
                    ->mapWithKeys(fn (ProductType $type): array => [
                        $type->value => (int) ($serviceCountsByType[$type->value] ?? 0),
                    ])
                    ->all(),
                'pendingBookings' => $pendingBookings,
                'activeBookings' => $activeBookings,
                'avgRating' => $avgRating ? number_format((float) $avgRating, 1) : '—',
                'walletBalance' => number_format($balanceMinor / 100, 0),
                'upcomingEvents' => $upcomingEvents,
                'nextDeadline' => $nextDeadline ? Carbon::parse($nextDeadline) : null,
            ];
        });

        $serviceTypeStats = collect(ProductType::cases())
            ->map(function (ProductType $type) use ($data): Stat {
                $resource = match ($type) {
                    ProductType::Rental => VendorRentalServiceResource::class,
                    ProductType::Sale => VendorSaleServiceResource::class,
                    ProductType::Digital => VendorDigitalServiceResource::class,
                };

                return Stat::make(
                    __('vendor-portal.dashboard.services_by_type.'.$type->value),
                    (string) ($data['serviceCountsByType'][$type->value] ?? 0),
                )
                    ->description(__('vendor-portal.dashboard.manage_services'))
                    ->descriptionIcon(match ($type) {
                        ProductType::Rental => 'heroicon-o-calendar-days',
                        ProductType::Sale => 'heroicon-o-shopping-bag',
                        ProductType::Digital => 'heroicon-o-cloud-arrow-down',
                    })
                    ->color(match ($type) {
                        ProductType::Rental => 'info',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'primary',
                    })
                    ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/vendor-portal' : $resource::getUrl());
            })
            ->all();

        return [
            Stat::make(
                __('vendor-portal.dashboard.revenue_this_month'),
                $data['revenueEgp'].' EGP',
            )
                ->description(now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/vendor-portal' : VendorWalletPage::getUrl()),

            Stat::make(
                __('vendor-portal.dashboard.bookings_this_month'),
                (string) $data['bookingsThisMonth'],
            )
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('info'),

            Stat::make(
                __('vendor-portal.dashboard.pending_bookings'),
                (string) $data['pendingBookings'],
            )
                ->descriptionIcon('heroicon-o-clock')
                ->color($data['pendingBookings'] > 0 ? 'warning' : 'gray')
                ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/vendor-portal' : VendorIncomingBookingsPage::getUrl()),

            Stat::make(
                __('vendor-portal.dashboard.active_bookings'),
                (string) $data['activeBookings'],
            )
                ->descriptionIcon('heroicon-o-play-circle')
                ->color('primary')
                ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/vendor-portal' : VendorActiveBookingsPage::getUrl()),

            Stat::make(
                __('vendor-portal.dashboard.pending_services'),
                (string) $data['pendingServices'],
            )
                ->descriptionIcon('heroicon-o-square-3-stack-3d')
                ->color($data['pendingServices'] > 0 ? 'warning' : 'gray'),

            Stat::make(
                __('vendor-portal.dashboard.average_rating'),
                $data['avgRating'],
            )
                ->descriptionIcon('heroicon-o-star')
                ->color('warning'),

            Stat::make(
                __('vendor-portal.dashboard.wallet_balance'),
                $data['walletBalance'].' EGP',
            )
                ->description(__('vendor-portal.dashboard.available_balance'))
                ->descriptionIcon('heroicon-o-wallet')
                ->color('success')
                ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/vendor-portal' : VendorWalletPage::getUrl()),

            Stat::make(
                __('vendor-portal.dashboard.upcoming_events'),
                (string) $data['upcomingEvents'],
            )
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info')
                ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/vendor-portal' : VendorBookingsPage::getUrl(['statusFilter' => 'accepted'])),

            Stat::make(
                __('vendor-portal.dashboard.sla_countdown'),
                $data['nextDeadline'] !== null
                    ? DeadlineColumnFormatter::format($data['nextDeadline'])
                    : '—',
            )
                ->descriptionIcon('heroicon-o-clock')
                ->color($data['nextDeadline'] !== null ? 'warning' : 'gray')
                ->url(env('MINIMAL_FILAMENT_PANELS', false) ? '/vendor-portal' : VendorBookingsPage::getUrl(['statusFilter' => 'pending'])),
            ...$serviceTypeStats,
        ];
    }
}

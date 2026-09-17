<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Widgets;

use App\Modules\Booking\Application\Actions\GetNegotiationMonitorQueryAction;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AdminDashboardHeaderWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -99;

    protected function getStats(): array
    {
        $monitorAccess = auth()->user()?->can('view_any_bookings::monitor') ?? false;

        $metrics = Cache::remember('admin_dashboard_header_metrics:'.(int) $monitorAccess, 120, function () use ($monitorAccess): array {
            $pendingVendors = VendorProfile::query()
                ->where('approval_status', ApprovalStatus::Pending->value)
                ->count();

            $overdueResponses = $monitorAccess
                ? app(GetNegotiationMonitorQueryAction::class)->execute('late')->count()
                : 0;

            $failedPaymentsToday = Payment::query()
                ->where('status', PaymentStatus::Failed->value)
                ->whereDate('created_at', today())
                ->count();

            $revenueToday = WalletLedgerEntry::query()
                ->whereDate('created_at', today())
                ->where('direction', 'credit')
                ->sum('amount_minor');

            return [
                'pendingVendors' => $pendingVendors,
                'overdueResponses' => $overdueResponses,
                'failedPaymentsToday' => $failedPaymentsToday,
                'revenueToday' => number_format($revenueToday / 100, 0),
            ];
        });

        return [
            Stat::make(
                __('identity::widgets.pending_vendor_approvals_heading'),
                number_format($metrics['pendingVendors']),
            )
                ->description(trans_choice(
                    'identity::widgets.pending_vendor_approvals_description',
                    $metrics['pendingVendors'],
                    ['count' => $metrics['pendingVendors']],
                ))
                ->descriptionIcon('heroicon-o-user-plus')
                ->color($metrics['pendingVendors'] > 0 ? 'warning' : 'gray'),

            Stat::make(
                __('booking::widgets.late_vendor_responses_heading'),
                number_format($metrics['overdueResponses']),
            )
                ->description(trans_choice(
                    'booking::widgets.late_vendor_responses_description',
                    $metrics['overdueResponses'],
                    ['count' => $metrics['overdueResponses']],
                ))
                ->descriptionIcon('heroicon-o-clock')
                ->color($metrics['overdueResponses'] > 0 ? 'danger' : 'success')
                ->url($monitorAccess
                    ? BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=late'
                    : null),

            Stat::make(
                __('payments::widgets.failed_payments_heading'),
                number_format($metrics['failedPaymentsToday']),
            )
                ->description(trans_choice(
                    'payments::widgets.failed_payments_description',
                    $metrics['failedPaymentsToday'],
                    ['count' => $metrics['failedPaymentsToday']],
                ))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($metrics['failedPaymentsToday'] > 0 ? 'danger' : 'success'),

            Stat::make(
                __('admin.dashboard.revenue_today'),
                $metrics['revenueToday'].' '.__('admin.common.egp'),
            )
                ->description(__('admin.dashboard.live_summary'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info'),
        ];
    }
}

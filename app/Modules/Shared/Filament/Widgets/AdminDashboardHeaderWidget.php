<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Widgets;

use App\Modules\Booking\Application\Actions\GetNegotiationMonitorQueryAction;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
use App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AdminDashboardHeaderWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -99;

    protected function getStats(): array
    {
        $access = [
            'vendors' => VendorProfileResource::canViewAny(),
            'monitor' => BookingsMonitorResource::canViewAny(),
            'payments' => PaymentResource::canViewAny(),
            'wallet_ledger' => WalletLedgerViewerResource::canViewAny(),
        ];
        $userId = (string) (auth()->id() ?? 'guest');
        $accessFingerprint = implode('', array_map(
            static fn (bool $allowed): string => $allowed ? '1' : '0',
            array_values($access),
        ));

        $metrics = Cache::remember("admin_dashboard_header_metrics:{$userId}:{$accessFingerprint}", 120, function () use ($access): array {
            $pendingVendors = $access['vendors']
                ? VendorProfile::query()->where('approval_status', ApprovalStatus::Pending->value)->count()
                : 0;

            $overdueResponses = $access['monitor']
                ? app(GetNegotiationMonitorQueryAction::class)->execute('late')->count()
                : 0;

            $failedPaymentsToday = $access['payments']
                ? Payment::query()->where('status', PaymentStatus::Failed->value)->whereDate('created_at', today())->count()
                : 0;

            $revenueToday = $access['wallet_ledger']
                ? WalletLedgerEntry::query()->whereDate('created_at', today())->where('direction', 'credit')->sum('amount_minor')
                : 0;

            return [
                'pendingVendors' => $pendingVendors,
                'overdueResponses' => $overdueResponses,
                'failedPaymentsToday' => $failedPaymentsToday,
                'revenueToday' => number_format($revenueToday / 100, 0),
            ];
        });

        $stats = [];

        if ($access['vendors']) {
            $stats[] = Stat::make(
                __('identity::widgets.pending_vendor_approvals_heading'),
                number_format($metrics['pendingVendors']),
            )
                ->description(trans_choice(
                    'identity::widgets.pending_vendor_approvals_description',
                    $metrics['pendingVendors'],
                    ['count' => $metrics['pendingVendors']],
                ))
                ->descriptionIcon('heroicon-o-user-plus')
                ->color($metrics['pendingVendors'] > 0 ? 'warning' : 'gray');
        }

        if ($access['monitor']) {
            $stats[] = Stat::make(
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
                ->url(BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=late');
        }

        if ($access['payments']) {
            $stats[] = Stat::make(
                __('payments::widgets.failed_payments_heading'),
                number_format($metrics['failedPaymentsToday']),
            )
                ->description(trans_choice(
                    'payments::widgets.failed_payments_description',
                    $metrics['failedPaymentsToday'],
                    ['count' => $metrics['failedPaymentsToday']],
                ))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($metrics['failedPaymentsToday'] > 0 ? 'danger' : 'success');
        }

        if ($access['wallet_ledger']) {
            $stats[] = Stat::make(
                __('admin.dashboard.revenue_today'),
                $metrics['revenueToday'].' '.__('admin.common.egp'),
            )
                ->description(__('admin.dashboard.live_summary'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info');
        }

        return $stats;
    }
}

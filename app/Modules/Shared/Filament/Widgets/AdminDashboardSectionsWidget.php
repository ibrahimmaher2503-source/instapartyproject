<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Widgets;

use App\Modules\Booking\Application\Actions\GetNegotiationMonitorQueryAction;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource;
use App\Modules\Booking\Filament\Resources\BookingModificationResource;
use App\Modules\Booking\Filament\Resources\BookingResource;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Filament\Resources\NotificationDispatchResource;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use App\Modules\Discovery\Filament\Resources\PackageRecommendationResource;
use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use App\Modules\Payments\Filament\Resources\RefundResource;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource;
use Brick\Money\Money;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class AdminDashboardSectionsWidget extends Widget
{
    protected static string $view = 'filament.widgets.admin-dashboard-sections';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -100;

    public function getViewData(): array
    {
        $financialVisible = $this->canAny([
            'view_any_payment',
            'view_any_refund',
            'view_any_commission',
            'view_any_withdrawal',
            'view_any_wallet_ledger_entry',
        ]);

        $operationsVisible = $this->canAny([
            'view_any_booking',
            'view_any_bookings::monitor',
            'view_any_vendor_profile',
            'view_any_booking_modification',
            'view_any_admin_booking_intervention',
            'view_any_notification_dispatch',
        ]);

        $userId = (string) (auth()->id() ?? 'guest');
        $locale = app()->getLocale();

        return Cache::store('database')->remember(
            "admin_dashboard_content:{$userId}:{$locale}:".(int) $financialVisible.':'.(int) $operationsVisible,
            now()->addSeconds(30),
            fn (): array => [
                'financialVisible' => $financialVisible,
                'operationsVisible' => $operationsVisible,
                'financials' => $financialVisible ? $this->financialData() : [],
                'operations' => $operationsVisible ? $this->operationsData() : [],
                'homepagePackages' => $this->homepagePackageData(),
            ],
        );
    }

    private function homepagePackageData(): array
    {
        $visible = PackageRecommendationResource::canViewAny();

        return [
            'visible' => $visible,
            'published' => $visible ? PackageRecommendation::query()->published()->count() : 0,
            'drafts' => $visible ? PackageRecommendation::query()->where('is_published', false)->count() : 0,
            'url' => $visible ? PackageRecommendationResource::getUrl('index') : null,
            'create_url' => $visible ? PackageRecommendationResource::getUrl('create') : null,
        ];
    }

    private function financialData(): array
    {
        $currency = 'EGP';
        $capturedMinor = (int) Payment::query()->where('status', PaymentStatus::Captured->value)->sum('amount_minor');
        $refundMinor = (int) Refund::query()->where('status', RefundStatus::Completed->value)->sum('amount_minor');
        $commissionMinor = (int) Commission::query()->sum('commission_minor');
        $pendingPaymentsMinor = (int) Payment::query()->where('status', PaymentStatus::Pending->value)->sum('amount_minor');
        $payablesMinor = (int) Withdrawal::query()
            ->whereIn('status', [WithdrawalStatus::Pending->value, WithdrawalStatus::Approved->value])
            ->sum('requested_amount_minor');

        $netRevenue = Money::ofMinor($capturedMinor, $currency)->minus(Money::ofMinor($refundMinor, $currency));

        return [
            'kpis' => [
                $this->metric(__('shared::dashboard.financials.gross_revenue'), $this->formatMinor($capturedMinor, $currency), 'heroicon-o-banknotes', 'info', description: __('shared::dashboard.financials.captured_payments_formula')),
                $this->metric(__('shared::dashboard.financials.net_revenue'), $netRevenue->formatTo(app()->getLocale()), 'heroicon-o-arrow-trending-up', 'success', description: __('shared::dashboard.financials.net_collected_formula')),
                $this->metric(__('shared::dashboard.financials.platform_commission'), $this->formatMinor($commissionMinor, $currency), 'heroicon-o-building-office-2', 'primary', description: __('shared::dashboard.financials.platform_commission_formula')),
                $this->metric(__('shared::dashboard.financials.pending_payments'), $this->formatMinor($pendingPaymentsMinor, $currency), 'heroicon-o-clock', 'warning', PaymentResource::getUrl('index').'?tableFilters[status][value]=pending', description: __('shared::dashboard.financials.pending_payments_formula')),
                $this->metric(__('shared::dashboard.financials.vendor_payables'), $this->formatMinor($payablesMinor, $currency), 'heroicon-o-arrow-up-tray', 'warning', WithdrawalsQueueResource::getUrl('index')),
            ],
            'chart' => [
                'title' => __('shared::dashboard.financials.trend_title'),
                'caption' => __('shared::dashboard.financials.trend_caption'),
                'series' => $this->financialSeries(7),
            ],
            'summary' => [
                ['label' => __('shared::dashboard.financials.gross_revenue'), 'value' => $this->formatMinor($capturedMinor, $currency), 'tone' => 'info'],
                ['label' => __('shared::dashboard.financials.platform_commission'), 'value' => $this->formatMinor($commissionMinor, $currency), 'tone' => 'primary'],
                ['label' => __('shared::dashboard.financials.refunds'), 'value' => $this->formatMinor($refundMinor, $currency), 'tone' => 'danger'],
                ['label' => __('shared::dashboard.financials.outstanding'), 'value' => $this->formatMinor($pendingPaymentsMinor, $currency), 'tone' => 'warning'],
            ],
            'vendors' => $this->topVendorsByRevenue($currency),
            'statuses' => [
                $this->statusMetric(__('shared::dashboard.financials.paid'), PaymentStatus::Captured->value, PaymentResource::getUrl('index').'?tableFilters[status][value]=captured', 'success'),
                $this->statusMetric(__('shared::dashboard.financials.pending'), PaymentStatus::Pending->value, PaymentResource::getUrl('index').'?tableFilters[status][value]=pending', 'warning'),
                $this->statusMetric(__('shared::dashboard.financials.refunded'), PaymentStatus::Refunded->value, PaymentResource::getUrl('index').'?tableFilters[status][value]=refunded', 'info'),
                $this->statusMetric(__('shared::dashboard.financials.failed'), PaymentStatus::Failed->value, PaymentResource::getUrl('index').'?tableFilters[status][value]=failed', 'danger'),
            ],
            'attention' => [
                $this->attentionMetric(__('shared::dashboard.attention.payment_failures'), (int) Payment::query()->where('status', PaymentStatus::Failed->value)->where('created_at', '>=', now()->subHours(48))->count(), PaymentResource::getUrl('index').'?tableFilters[status][value]=failed', 'danger'),
                $this->attentionMetric(__('shared::dashboard.attention.pending_withdrawals'), (int) Withdrawal::query()->where('status', WithdrawalStatus::Pending->value)->count(), WithdrawalsQueueResource::getUrl('index'), 'warning'),
                $this->attentionMetric(__('shared::dashboard.attention.refund_issues'), (int) Refund::query()->where('status', RefundStatus::Failed->value)->count(), RefundResource::getUrl('index'), 'danger'),
            ],
        ];
    }

    private function operationsData(): array
    {
        $bookingAccess = $this->canAny(['view_any_booking']);
        $monitorAccess = $this->canAny(['view_any_bookings::monitor']);
        $vendorAccess = $this->canAny(['view_any_vendor_profile']);
        $modificationAccess = $this->canAny(['view_any_booking_modification']);
        $interventionAccess = $this->canAny(['view_any_admin_booking_intervention']);
        $notificationAccess = $this->canAny(['view_any_notification_dispatch']);

        $lateResponses = $monitorAccess
            ? (int) app(GetNegotiationMonitorQueryAction::class)->execute('late')->count()
            : 0;

        $pendingRequests = $bookingAccess ? (int) Booking::query()->whereState('lifecycle_status', CustomerReviewState::class)->count() : 0;
        $pendingVendors = $vendorAccess ? (int) VendorProfile::query()->where('approval_status', ApprovalStatus::Pending->value)->count() : 0;
        $activeVendors = $vendorAccess ? (int) VendorProfile::query()->where('approval_status', ApprovalStatus::Approved->value)->count() : 0;
        $openNegotiations = $monitorAccess
            ? (int) app(GetNegotiationMonitorQueryAction::class)->execute('open')->count()
            : 0;
        $pendingModifications = $modificationAccess ? (int) BookingModification::query()->where('status', ModificationStatus::Pending->value)->count() : 0;
        $interventions = $interventionAccess ? (int) BookingAdminIntervention::query()->whereNull('customer_consent_status')->count() : 0;
        $providerIssues = $notificationAccess ? (int) NotificationDispatch::query()->whereIn('status', [DispatchStatus::Failed->value, DispatchStatus::Bounced->value])->where('created_at', '>=', now()->subHours(24))->count() : 0;

        return [
            'kpis' => [
                $this->metric(__('shared::dashboard.operations.total_bookings'), $bookingAccess ? (int) Booking::query()->count() : 0, 'heroicon-o-calendar-days', 'primary', $bookingAccess ? BookingResource::getUrl('index') : null, $bookingAccess),
                $this->metric(__('shared::dashboard.operations.new_requests'), $pendingRequests, 'heroicon-o-inbox-arrow-down', 'warning', $bookingAccess ? BookingResource::getUrl('index').'?tableFilters[lifecycle_status][value]=customer_review' : null, $bookingAccess),
                $this->metric(__('shared::dashboard.operations.pending_vendor_responses'), $lateResponses, 'heroicon-o-clock', 'danger', $monitorAccess ? BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=late' : null, $monitorAccess),
                $this->metric(__('shared::dashboard.operations.open_negotiations'), $openNegotiations, 'heroicon-o-chat-bubble-left-right', 'info', $monitorAccess ? BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=open' : null, $monitorAccess),
                $this->metric(__('shared::dashboard.operations.active_vendors'), $activeVendors, 'heroicon-o-building-storefront', 'success', $vendorAccess ? VendorApprovalQueueResource::getUrl('index') : null, $vendorAccess),
                $this->metric(__('shared::dashboard.operations.pending_modifications'), $pendingModifications, 'heroicon-o-pencil-square', 'warning', $modificationAccess ? BookingModificationResource::getUrl('index') : null, $modificationAccess),
                $this->metric(__('shared::dashboard.operations.interventions'), $interventions, 'heroicon-o-shield-exclamation', 'danger', $interventionAccess ? AdminBookingInterventionResource::getUrl('index') : null, $interventionAccess),
                $this->metric(__('shared::dashboard.operations.pending_approvals'), $pendingVendors, 'heroicon-o-user-plus', 'warning', $vendorAccess ? VendorApprovalQueueResource::getUrl('index') : null, $vendorAccess),
            ],
            'chart' => [
                'title' => __('shared::dashboard.operations.activity_title'),
                'caption' => __('shared::dashboard.operations.activity_caption'),
                'series' => $bookingAccess ? $this->operationsSeries() : [],
            ],
            'attention' => [
                $this->attentionMetric(__('shared::dashboard.attention.new_requests'), $pendingRequests, $bookingAccess ? BookingResource::getUrl('index').'?tableFilters[lifecycle_status][value]=customer_review' : null, 'warning', $bookingAccess),
                $this->attentionMetric(__('shared::dashboard.attention.late_responses'), $lateResponses, $monitorAccess ? BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=late' : null, 'danger', $monitorAccess),
                $this->attentionMetric(__('shared::dashboard.attention.pending_approvals'), $pendingVendors, $vendorAccess ? VendorApprovalQueueResource::getUrl('index') : null, 'warning', $vendorAccess),
                $this->attentionMetric(__('shared::dashboard.attention.provider_issues'), $providerIssues, $notificationAccess ? NotificationDispatchResource::getUrl('index') : null, 'danger', $notificationAccess),
            ],
            'upcoming' => $bookingAccess ? Booking::query()
                ->whereNotNull('event_starts_at')
                ->where('event_starts_at', '>=', now())
                ->whereNotIn('lifecycle_status', ['draft', 'cancelled'])
                ->withCount(['vendors', 'items'])
                ->orderBy('event_starts_at')
                ->limit(5)
                ->get()
                ->map(fn (Booking $booking): array => [
                    'reference' => (string) $booking->reference_no,
                    'date' => $booking->event_starts_at?->format('M d, Y · H:i'),
                    'services' => (int) $booking->items_count,
                    'vendors' => (int) $booking->vendors_count,
                    'status' => (string) $booking->lifecycle_status,
                ])->all() : [],
            'negotiations' => [
                ['label' => __('shared::dashboard.operations.pending_vendor_responses'), 'value' => $lateResponses, 'url' => $monitorAccess ? BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=late' : null],
                ['label' => __('shared::dashboard.operations.open_negotiations'), 'value' => $openNegotiations, 'url' => $monitorAccess ? BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=open' : null],
                ['label' => __('shared::dashboard.operations.pending_modifications'), 'value' => $pendingModifications, 'url' => $modificationAccess ? BookingModificationResource::getUrl('index') : null],
                ['label' => __('shared::dashboard.operations.interventions'), 'value' => $interventions, 'url' => $interventionAccess ? AdminBookingInterventionResource::getUrl('index') : null],
            ],
        ];
    }

    private function financialSeries(int $days): array
    {
        return [
            ['label' => __('shared::dashboard.financials.revenue'), 'color' => 'primary', 'values' => $this->dailyTotals(Payment::query()->where('status', PaymentStatus::Captured->value), 'amount_minor', $days)],
            ['label' => __('shared::dashboard.financials.commission'), 'color' => 'success', 'values' => $this->dailyTotals(Commission::query(), 'commission_minor', $days)],
            ['label' => __('shared::dashboard.financials.refunds'), 'color' => 'danger', 'values' => $this->dailyTotals(Refund::query()->where('status', RefundStatus::Completed->value), 'amount_minor', $days)],
        ];
    }

    private function operationsSeries(): array
    {
        return [
            ['label' => __('shared::dashboard.operations.bookings'), 'color' => 'primary', 'values' => $this->dailyCounts(Booking::query(), 7)],
            ['label' => __('shared::dashboard.operations.vendor_responses'), 'color' => 'success', 'values' => $this->dailyCounts(BookingVendor::query()->whereNotNull('responded_at'), 7, 'responded_at')],
            ['label' => __('shared::dashboard.operations.modifications'), 'color' => 'warning', 'values' => $this->dailyCounts(BookingModification::query(), 7)],
        ];
    }

    private function topVendorsByRevenue(string $currency): array
    {
        return Commission::query()
            ->selectRaw('vendor_profile_id, SUM(commission_minor) as commission_total, SUM(gross_amount_minor) as gross_total')
            ->with('vendor')
            ->groupBy('vendor_profile_id')
            ->orderByDesc('gross_total')
            ->limit(5)
            ->get()
            ->map(fn (Commission $commission): array => [
                'name' => $commission->vendor?->getTranslation('business_name', app()->getLocale()) ?? __('shared::dashboard.unknown_vendor'),
                'revenue' => $this->formatMinor((int) $commission->gross_total, $currency),
                'commission' => $this->formatMinor((int) $commission->commission_total, $currency),
            ])->all();
    }

    private function dailyTotals(Builder $query, string $column, int $days): array
    {
        $start = now()->startOfDay()->subDays($days - 1);
        $rows = $query->where('created_at', '>=', $start)
            ->selectRaw("DATE(created_at) as day, SUM({$column}) as total")
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))->map(fn (int $offset): array => [
            'label' => $start->copy()->addDays($offset)->format('M d'),
            'value' => (int) ($rows[$start->copy()->addDays($offset)->toDateString()] ?? 0),
        ])->all();
    }

    private function dailyCounts(Builder $query, int $days, string $column = 'created_at'): array
    {
        $start = now()->startOfDay()->subDays($days - 1);
        $rows = $query->where($column, '>=', $start)
            ->selectRaw("DATE({$column}) as day, COUNT(*) as total")
            ->groupByRaw("DATE({$column})")
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))->map(fn (int $offset): array => [
            'label' => $start->copy()->addDays($offset)->format('M d'),
            'value' => (int) ($rows[$start->copy()->addDays($offset)->toDateString()] ?? 0),
        ])->all();
    }

    private function metric(string $label, int|string $value, string $icon, string $tone, ?string $url = null, bool $visible = true, ?string $description = null): array
    {
        return compact('label', 'value', 'icon', 'tone', 'url', 'visible', 'description');
    }

    private function statusMetric(string $label, string $status, string $url, string $tone): array
    {
        return [
            'label' => $label,
            'value' => (int) Payment::query()->where('status', $status)->count(),
            'url' => $url,
            'tone' => $tone,
        ];
    }

    private function attentionMetric(string $label, int $value, ?string $url, string $tone, bool $visible = true): array
    {
        return compact('label', 'value', 'url', 'tone', 'visible');
    }

    private function formatMinor(int $minor, string $currency): string
    {
        return Money::ofMinor($minor, $currency)->formatTo(app()->getLocale());
    }

    private function canAny(array $permissions): bool
    {
        return auth()->user()?->canAny($permissions) ?? false;
    }
}

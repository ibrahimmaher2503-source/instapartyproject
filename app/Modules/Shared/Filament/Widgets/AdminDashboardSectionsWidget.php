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
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use App\Modules\Payments\Filament\Resources\RefundResource;
use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\CommissionResource;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource;
use App\Modules\Shared\Domain\Models\StateTransition;
use Brick\Money\Money;
use Carbon\CarbonInterface;
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
        $financialAccess = [
            'payments' => PaymentResource::canViewAny(),
            'refunds' => RefundResource::canViewAny(),
            'commissions' => CommissionResource::canViewAny(),
            'withdrawals' => WithdrawalsQueueResource::canViewAny(),
        ];
        $operationsAccess = [
            'bookings' => BookingResource::canViewAny(),
            'monitor' => BookingsMonitorResource::canViewAny(),
            'vendors' => VendorProfileResource::canViewAny(),
            'modifications' => BookingModificationResource::canViewAny(),
            'interventions' => AdminBookingInterventionResource::canViewAny(),
            'notifications' => NotificationDispatchResource::canViewAny(),
        ];
        $homepagePackagesVisible = PackageRecommendationResource::canViewAny();
        $financialVisible = in_array(true, $financialAccess, true);
        $operationsVisible = in_array(true, $operationsAccess, true);
        $accessFingerprint = implode('', array_map(
            static fn (bool $allowed): string => $allowed ? '1' : '0',
            array_merge(array_values($financialAccess), array_values($operationsAccess), [$homepagePackagesVisible]),
        ));

        $userId = (string) (auth()->id() ?? 'guest');
        $locale = app()->getLocale();

        return Cache::store('database')->remember(
            "admin_dashboard_content:{$userId}:{$locale}:{$accessFingerprint}",
            now()->addSeconds(30),
            fn (): array => [
                'upcomingBookingsVisible' => $operationsAccess['bookings'],
                'negotiationMonitoringVisible' => $operationsAccess['monitor'],
                'vendorOperationsVisible' => $operationsAccess['vendors'],
                'financialVisible' => $financialVisible,
                'operationsVisible' => $operationsVisible,
                'financials' => $financialVisible ? $this->financialData($financialAccess) : [],
                'operations' => $operationsVisible ? $this->operationsData($operationsAccess) : [],
                'homepagePackages' => $this->homepagePackageData($homepagePackagesVisible),
            ],
        );
    }

    private function homepagePackageData(bool $visible): array
    {
        return [
            'visible' => $visible,
            'published' => $visible ? PackageRecommendation::query()->published()->count() : 0,
            'drafts' => $visible ? PackageRecommendation::query()->where('is_published', false)->count() : 0,
            'url' => $visible ? PackageRecommendationResource::getUrl('index') : null,
            'create_url' => $visible ? PackageRecommendationResource::getUrl('create') : null,
        ];
    }

    private function financialData(array $access): array
    {
        $currency = 'EGP';
        $now = now('UTC');
        $currentStart = $now->copy()->startOfDay()->subDays(6);
        $capturedMinor = $access['payments']
            ? (int) Payment::query()->where('amount_currency', $currency)->where('status', PaymentStatus::Captured->value)->sum('amount_minor')
            : 0;
        $refundMinor = $access['refunds']
            ? (int) Refund::query()->where('amount_currency', $currency)->where('status', RefundStatus::Completed->value)->sum('amount_minor')
            : 0;
        $commissionMinor = $access['commissions']
            ? (int) Commission::query()->where('commission_currency', $currency)->sum('commission_minor')
            : 0;
        $pendingPaymentsMinor = $access['payments']
            ? (int) Payment::query()->where('amount_currency', $currency)->where('status', PaymentStatus::Pending->value)->sum('amount_minor')
            : 0;
        $payablesMinor = $access['withdrawals']
            ? (int) Withdrawal::query()->where('requested_amount_currency', $currency)
                ->whereIn('status', [WithdrawalStatus::Pending->value, WithdrawalStatus::Approved->value])
                ->sum('requested_amount_minor')
            : 0;

        $kpis = [];
        $summary = [];
        $statuses = [];
        $attention = [];

        if ($access['payments']) {
            $kpis[] = $this->metric(__('shared::dashboard.financials.gross_revenue'), $this->formatMinor($capturedMinor, $currency), 'heroicon-o-banknotes', 'info', description: __('shared::dashboard.financials.captured_payments_formula'));
            $summary[] = ['label' => __('shared::dashboard.financials.gross_revenue'), 'value' => $this->formatMinor($capturedMinor, $currency), 'tone' => 'info'];

            $statuses = [
                $this->statusMetric(__('shared::dashboard.financials.paid'), PaymentStatus::Captured->value, PaymentResource::getUrl('index').'?tableFilters[status][value]=captured', 'success'),
                $this->statusMetric(__('shared::dashboard.financials.pending'), PaymentStatus::Pending->value, PaymentResource::getUrl('index').'?tableFilters[status][value]=pending', 'warning'),
                $this->statusMetric(__('shared::dashboard.financials.refunded'), PaymentStatus::Refunded->value, PaymentResource::getUrl('index').'?tableFilters[status][value]=refunded', 'info'),
                $this->statusMetric(__('shared::dashboard.financials.failed'), PaymentStatus::Failed->value, PaymentResource::getUrl('index').'?tableFilters[status][value]=failed', 'danger'),
            ];
            $attention[] = $this->attentionMetric(__('shared::dashboard.attention.payment_failures'), (int) Payment::query()->where('status', PaymentStatus::Failed->value)->where('created_at', '>=', now()->subHours(48))->count(), PaymentResource::getUrl('index').'?tableFilters[status][value]=failed', 'danger');
        }

        if ($access['payments'] && $access['refunds']) {
            $netRevenue = Money::ofMinor($capturedMinor, $currency)->minus(Money::ofMinor($refundMinor, $currency));
            $kpis[] = $this->metric(__('shared::dashboard.financials.net_revenue'), $netRevenue->formatTo(app()->getLocale()), 'heroicon-o-arrow-trending-up', 'success', description: __('shared::dashboard.financials.net_collected_formula'));
        }

        if ($access['commissions']) {
            $kpis[] = $this->metric(__('shared::dashboard.financials.platform_commission'), $this->formatMinor($commissionMinor, $currency), 'heroicon-o-building-office-2', 'primary', description: __('shared::dashboard.financials.platform_commission_formula'));
            $summary[] = ['label' => __('shared::dashboard.financials.platform_commission'), 'value' => $this->formatMinor($commissionMinor, $currency), 'tone' => 'primary'];
        }

        if ($access['refunds']) {
            $summary[] = ['label' => __('shared::dashboard.financials.refunds'), 'value' => $this->formatMinor($refundMinor, $currency), 'tone' => 'danger'];
        }

        if ($access['payments']) {
            $kpis[] = $this->metric(__('shared::dashboard.financials.pending_payments'), $this->formatMinor($pendingPaymentsMinor, $currency), 'heroicon-o-clock', 'warning', PaymentResource::getUrl('index').'?tableFilters[status][value]=pending', description: __('shared::dashboard.financials.pending_payments_formula'));
            $summary[] = ['label' => __('shared::dashboard.financials.outstanding'), 'value' => $this->formatMinor($pendingPaymentsMinor, $currency), 'tone' => 'warning'];
        }

        if ($access['withdrawals']) {
            $kpis[] = $this->metric(__('shared::dashboard.financials.vendor_payables'), $this->formatMinor($payablesMinor, $currency), 'heroicon-o-arrow-up-tray', 'warning', WithdrawalsQueueResource::getUrl('index'));
            $attention[] = $this->attentionMetric(__('shared::dashboard.attention.pending_withdrawals'), (int) Withdrawal::query()->where('requested_amount_currency', $currency)->where('status', WithdrawalStatus::Pending->value)->count(), WithdrawalsQueueResource::getUrl('index'), 'warning');
        }

        if ($access['refunds']) {
            $attention[] = $this->attentionMetric(__('shared::dashboard.attention.refund_issues'), (int) Refund::query()->where('amount_currency', $currency)->where('status', RefundStatus::Failed->value)->count(), RefundResource::getUrl('index'), 'danger');
        }

        $financialSeries = $this->financialSeries(7, $access, $now);

        return [
            'comparison' => $access['payments'] ? __('shared::panel.comparison', [
                'current' => $this->formatMinor((int) Payment::query()->where('amount_currency', $currency)->where('status', PaymentStatus::Captured->value)->whereBetween('created_at', [$currentStart, $now])->sum('amount_minor'), $currency),
                'previous' => $this->formatMinor((int) Payment::query()->where('amount_currency', $currency)->where('status', PaymentStatus::Captured->value)->whereBetween('created_at', [$currentStart->copy()->subDays(7), $currentStart->copy()->subMicrosecond()])->sum('amount_minor'), $currency),
            ]) : null,
            'chart' => [
                'title' => __('shared::dashboard.financials.trend_title'),
                'caption' => __('shared::dashboard.financials.trend_caption'),
                'series' => $financialSeries,
            ],
            'chartVisible' => $financialSeries !== [],
            'kpis' => $kpis,
            'summary' => $summary,
            'vendorsVisible' => $access['commissions'],
            'vendors' => $access['commissions'] ? $this->topVendorsByRevenue($currency) : [],
            'payablesVisible' => $access['withdrawals'],
            'payables' => $access['withdrawals'] ? $this->formatMinor($payablesMinor, $currency) : null,
            'statuses' => $statuses,
            'attention' => $attention,
        ];
    }

    private function operationsData(array $access): array
    {
        $bookingAccess = $access['bookings'];
        $monitorAccess = $access['monitor'];
        $vendorAccess = $access['vendors'];
        $modificationAccess = $access['modifications'];
        $interventionAccess = $access['interventions'];
        $notificationAccess = $access['notifications'];

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
                'series' => $this->operationsSeries($access),
            ],
            'booking_funnel' => $bookingAccess ? $this->bookingFunnel(now('UTC')) : null,
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
                    'status' => __('booking.lifecycle_status.'.(string) $booking->lifecycle_status),
                    'url' => BookingResource::getUrl('view', ['record' => $booking]),
                ])->all() : [],
            'negotiations' => [
                ['label' => __('shared::dashboard.operations.pending_vendor_responses'), 'value' => $lateResponses, 'url' => $monitorAccess ? BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=late' : null],
                ['label' => __('shared::dashboard.operations.open_negotiations'), 'value' => $openNegotiations, 'url' => $monitorAccess ? BookingsMonitorResource::getUrl('index').'?tableFilters[negotiation_scope][value]=open' : null],
                ['label' => __('shared::dashboard.operations.pending_modifications'), 'value' => $pendingModifications, 'url' => $modificationAccess ? BookingModificationResource::getUrl('index') : null],
                ['label' => __('shared::dashboard.operations.interventions'), 'value' => $interventions, 'url' => $interventionAccess ? AdminBookingInterventionResource::getUrl('index') : null],
            ],
        ];
    }

    private function bookingFunnel(CarbonInterface $now): array
    {
        $windowEnd = $now->copy()->utc();
        $windowStart = $windowEnd->copy()->startOfDay()->subDays(6);
        $window = [$windowStart, $windowEnd];

        $submittedRecords = (int) Booking::query()
            ->whereBetween('submitted_at', $window)
            ->count();
        $trackedSubmissionsQuery = Booking::query()
            ->whereBetween('submitted_at', $window)
            ->whereHas('stateTransitions', fn (Builder $query): Builder => $query
                ->where('from_state', 'draft')
                ->where('to_state', 'vendor_review')
                ->whereBetween('created_at', $window));
        $trackedSubmissions = (int) (clone $trackedSubmissionsQuery)->count();

        $stages = [[
            'key' => 'submitted',
            'count' => $trackedSubmissions,
        ]];

        foreach ([
            'confirmed' => 'confirmed',
            'active' => 'active',
            'completed' => 'completed',
        ] as $key => $state) {
            $count = (int) StateTransition::query()
                ->where('transitionable_type', Booking::class)
                ->where('to_state', $state)
                ->where('created_at', '<=', $windowEnd)
                ->whereIn('transitionable_id', (clone $trackedSubmissionsQuery)->select('bookings.id'))
                ->whereExists(function (\Illuminate\Database\Query\Builder $query): void {
                    $query->selectRaw('1')
                        ->from('state_transitions as booking_submission')
                        ->whereColumn('booking_submission.transitionable_id', 'state_transitions.transitionable_id')
                        ->where('booking_submission.transitionable_type', Booking::class)
                        ->where('booking_submission.from_state', 'draft')
                        ->where('booking_submission.to_state', 'vendor_review')
                        ->whereColumn('state_transitions.created_at', '>=', 'booking_submission.created_at');
                })
                ->distinct('transitionable_id')
                ->count('transitionable_id');

            $stages[] = [
                'key' => $key,
                'count' => $count,
            ];
        }

        foreach ($stages as &$stage) {
            $stage['conversion_percent'] = $trackedSubmissions > 0
                ? round(($stage['count'] / $trackedSubmissions) * 100, 1)
                : null;
            $stage['bar_percent'] = $trackedSubmissions > 0
                ? round(($stage['count'] / $trackedSubmissions) * 100, 1)
                : 0.0;
        }
        unset($stage);

        return [
            'window_start' => $windowStart->toDateString(),
            'window_end' => $windowEnd->toDateString(),
            'submitted_records' => $submittedRecords,
            'tracked_submissions' => $trackedSubmissions,
            'transition_coverage_percent' => $submittedRecords > 0
                ? round(($trackedSubmissions / $submittedRecords) * 100, 1)
                : null,
            'coverage_gap' => max(0, $submittedRecords - $trackedSubmissions),
            'stages' => $stages,
        ];
    }

    private function financialSeries(int $days, array $access, CarbonInterface $now): array
    {
        $series = [];

        if ($access['payments']) {
            $series[] = ['label' => __('shared::dashboard.financials.revenue'), 'color' => 'primary', 'values' => $this->dailyTotals(Payment::query()->where('amount_currency', 'EGP')->where('status', PaymentStatus::Captured->value), 'amount_minor', $days, $now)];
        }

        if ($access['commissions']) {
            $series[] = ['label' => __('shared::dashboard.financials.commission'), 'color' => 'success', 'values' => $this->dailyTotals(Commission::query()->where('commission_currency', 'EGP'), 'commission_minor', $days, $now)];
        }

        if ($access['refunds']) {
            $series[] = ['label' => __('shared::dashboard.financials.refunds'), 'color' => 'danger', 'values' => $this->dailyTotals(Refund::query()->where('amount_currency', 'EGP')->where('status', RefundStatus::Completed->value), 'amount_minor', $days, $now)];
        }

        return $series;
    }

    private function operationsSeries(array $access): array
    {
        $series = [];

        if ($access['bookings']) {
            $series[] = ['label' => __('shared::dashboard.operations.bookings'), 'color' => 'primary', 'values' => $this->dailyCounts(Booking::query(), 7)];
        }

        if ($access['monitor']) {
            $series[] = ['label' => __('shared::dashboard.operations.vendor_responses'), 'color' => 'success', 'values' => $this->dailyCounts(BookingVendor::query()->whereNotNull('responded_at'), 7, 'responded_at')];
        }

        if ($access['modifications']) {
            $series[] = ['label' => __('shared::dashboard.operations.modifications'), 'color' => 'warning', 'values' => $this->dailyCounts(BookingModification::query(), 7)];
        }

        return $series;
    }

    private function topVendorsByRevenue(string $currency): array
    {
        return Commission::query()
            ->where('gross_amount_currency', $currency)
            ->where('commission_currency', $currency)
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

    private function dailyTotals(Builder $query, string $column, int $days, CarbonInterface $now): array
    {
        $start = $now->copy()->startOfDay()->subDays($days - 1);
        $rows = $query->whereBetween('created_at', [$start, $now])
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
        $now = now('UTC');
        $start = $now->copy()->startOfDay()->subDays($days - 1);
        $rows = $query->whereBetween($column, [$start, $now])
            ->selectRaw("DATE({$column}) as day, COUNT(*) as total")
            ->groupByRaw("DATE({$column})")
            ->toBase()
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
}

<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalAnalyticsService
{
    /** @return array<string, array<string, int|null|string>> */
    public function summarize(int $days): array
    {
        if (! in_array($days, [7, 30, 90], true)) {
            throw ValidationException::withMessages(['days' => __('shared::operations.period')]);
        }
        $end = CarbonImmutable::now('UTC');
        $start = $end->subDays($days);

        return ['current' => $this->period($start, $end), 'previous' => $this->period($start->subDays($days), $start)];
    }

    /** @return array<string, int|null|string> */
    private function period(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $cohort = DB::table('bookings')->whereNull('deleted_at')->where('created_at', '>=', $start)->where('created_at', '<', $end);
        $created = (clone $cohort)->count();
        $submitted = (clone $cohort)->whereNotNull('submitted_at')->count();
        $paid = (clone $cohort)->whereNotNull('submitted_at')->whereExists(fn ($query) => $query->selectRaw('1')->from('payments')
            ->whereColumn('payments.booking_id', 'bookings.id')->whereNotNull('payments.captured_at'))->count();
        $vendors = DB::table('vendor_profiles')->where('created_at', '>=', $start)->where('created_at', '<', $end);

        return [
            'from' => $start->toIso8601String(), 'until' => $end->toIso8601String(),
            'drafts' => $created, 'submitted' => $submitted, 'paid' => $paid,
            'completed' => (clone $cohort)->where('lifecycle_status', 'completed')->count(),
            'cancelled' => (clone $cohort)->where('lifecycle_status', 'cancelled')->count(),
            'active_vendors' => DB::table('booking_vendors')->whereIn('booking_id', (clone $cohort)->whereNotNull('submitted_at')->select('id'))
                ->distinct()->count('vendor_profile_id'),
            'new_vendors' => (clone $vendors)->count(),
            'approved_vendors' => (clone $vendors)->where('approval_status', 'approved')->count(),
            'conversion' => $created === 0 ? null : (int) round($submitted * 100 / $created),
            'payment_conversion' => $submitted === 0 ? null : (int) round($paid * 100 / $submitted),
        ];
    }
}

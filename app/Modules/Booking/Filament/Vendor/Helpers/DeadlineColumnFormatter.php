<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Helpers;

use Carbon\Carbon;

final class DeadlineColumnFormatter
{
    private const SLA_THRESHOLD_SECONDS = 43200; // 12 hours

    public static function format(?Carbon $deadline): string
    {
        if ($deadline === null) {
            return '—';
        }

        if ($deadline->isPast()) {
            return __('vendor-portal.bookings.deadline_expired_label');
        }

        $secondsRemaining = (int) now()->diffInSeconds($deadline, absolute: false);

        if ($secondsRemaining <= self::SLA_THRESHOLD_SECONDS) {
            $hours = (int) now()->diffInHours($deadline);
            $minutes = (int) (now()->diffInMinutes($deadline) % 60);

            return __('vendor-portal.bookings.deadline_countdown', [
                'hours' => $hours,
                'minutes' => $minutes,
            ]);
        }

        return $deadline->format('d M Y H:i');
    }

    public static function color(?Carbon $deadline): string
    {
        if ($deadline === null) {
            return 'gray';
        }

        if ($deadline->isPast()) {
            return 'danger';
        }

        $secondsRemaining = (int) now()->diffInSeconds($deadline, absolute: false);

        return $secondsRemaining <= self::SLA_THRESHOLD_SECONDS ? 'danger' : 'warning';
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Booking\Console\Commands;

use App\Modules\Booking\Application\Actions\ExpireStaleVendorReviewsAction;
use Illuminate\Console\Command;

final class ExpireStaleVendorReviewsCommand extends Command
{
    protected $signature = 'booking:expire-stale-vendor-reviews {--limit=100 : Maximum bookings per run}';

    protected $description = 'Expire overdue vendor allocations and close vendor-review bookings whose events started';

    public function handle(ExpireStaleVendorReviewsAction $action): int
    {
        if (! config('booking.vendor_review_expiry.enabled', false)) {
            $this->warn('Vendor-review expiry is disabled.');

            return self::SUCCESS;
        }

        $limit = max(1, min((int) $this->option('limit'), 1000));
        $result = $action->execute($limit);

        $this->info(sprintf(
            'Processed %d booking(s): %d vendor timeout(s), %d moved to customer review, %d cancelled.',
            $result['bookings'], $result['vendors'], $result['customer_review'], $result['cancelled'],
        ));

        return self::SUCCESS;
    }
}

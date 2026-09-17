<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Commands;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SendDailyReviewSummaryCommand extends Command
{
    protected $signature = 'reviews:daily-summary {--date= : ISO date to summarise (default: yesterday)}';

    protected $description = 'Send a daily review digest email to all admin users';

    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))
            : now()->subDay();

        $serviceCount = ServiceReview::query()->whereDate('created_at', $date)->count();
        $vendorCount = VendorReview::query()->whereDate('created_at', $date)->count();
        $total = $serviceCount + $vendorCount;

        if ($total === 0) {
            $this->info("No reviews on {$date->toDateString()} — skipping digest.");

            return self::SUCCESS;
        }

        $pendingCount = ServiceReview::query()->pending()->count()
            + VendorReview::query()->pending()->count();

        $avgRating = $this->weightedAverage($date->toDateString(), $serviceCount, $vendorCount);

        $context = [
            'date' => $date->toDateString(),
            'service_review_count' => $serviceCount,
            'vendor_review_count' => $vendorCount,
            'total_count' => $total,
            'average_rating' => number_format($avgRating, 1),
            'pending_count' => $pendingCount,
        ];

        $dispatched = 0;

        foreach ($this->adminUserIds() as $userId) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'review.daily_summary',
                channel: NotificationChannel::Email,
                audience: NotificationAudience::Admin,
                eventCategory: EventCategory::Review,
                userId: $userId,
                context: $context,
            ));
            $dispatched++;
        }

        $this->info("Daily review digest for {$date->toDateString()} dispatched to {$dispatched} admin(s).");

        return self::SUCCESS;
    }

    private function weightedAverage(string $date, int $serviceCount, int $vendorCount): float
    {
        $total = $serviceCount + $vendorCount;

        if ($total === 0) {
            return 0.0;
        }

        $serviceSum = (float) ServiceReview::query()->whereDate('created_at', $date)->sum('rating');
        $vendorSum = (float) VendorReview::query()->whereDate('created_at', $date)->sum('rating');

        return round(($serviceSum + $vendorSum) / $total, 2);
    }

    /** @return int[] */
    private function adminUserIds(): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', 'App\\Modules\\Identity\\Domain\\Models\\User')
            ->whereIn('roles.name', ['admin', 'super_admin'])
            ->pluck('model_has_roles.model_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

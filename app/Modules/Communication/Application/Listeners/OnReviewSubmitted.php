<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Reviews\Domain\Events\ReviewSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class OnReviewSubmitted implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(ReviewSubmitted $event): void
    {
        $vendorUserId = $this->resolveVendorUserId($event->reviewType, $event->subjectId);

        if ($vendorUserId === null) {
            return;
        }

        $context = $this->buildContext($event);

        foreach ([NotificationChannel::InApp, NotificationChannel::Push] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'review.submitted',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::Review,
                userId: $vendorUserId,
                context: $context,
                referenceType: $event->reviewType === 'service' ? 'service_review' : 'vendor_review',
                referenceId: $event->reviewId,
            ));
        }
    }

    /**
     * Resolve the vendor's user_id without importing cross-module Eloquent models.
     * For service reviews: services.vendor_profile_id → vendor_profiles.user_id.
     * For vendor reviews: vendor_profiles.user_id directly.
     */
    private function resolveVendorUserId(string $reviewType, int $subjectId): ?int
    {
        if ($reviewType === 'service') {
            $vendorProfileId = DB::table('services')
                ->where('id', $subjectId)
                ->value('vendor_profile_id');

            if ($vendorProfileId === null) {
                return null;
            }

            return (int) DB::table('vendor_profiles')
                ->where('id', $vendorProfileId)
                ->value('user_id') ?: null;
        }

        // vendor review: subjectId is already vendor_profile_id
        return (int) DB::table('vendor_profiles')
            ->where('id', $subjectId)
            ->value('user_id') ?: null;
    }

    private function buildContext(ReviewSubmitted $event): array
    {
        $subjectName = $this->resolveSubjectName($event->reviewType, $event->subjectId);

        return [
            'review_public_id' => $event->reviewPublicId,
            'review_type' => $event->reviewType,
            'rating' => $event->rating,
            'subject_name' => $subjectName,
            'submitted_at' => $event->submittedAt->format('Y-m-d H:i:s'),
        ];
    }

    private function resolveSubjectName(string $reviewType, int $subjectId): string
    {
        if ($reviewType === 'service') {
            $raw = DB::table('services')->where('id', $subjectId)->value('name');
            $decoded = is_string($raw) ? json_decode($raw, true) : null;

            return is_array($decoded) ? ($decoded['en'] ?? '') : '';
        }

        $raw = DB::table('vendor_profiles')->where('id', $subjectId)->value('business_name');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? ($decoded['en'] ?? '') : '';
    }
}

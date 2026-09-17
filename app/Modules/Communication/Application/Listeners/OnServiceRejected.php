<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Catalog\Domain\Events\ServiceRejected;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnServiceRejected implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(ServiceRejected $event): void
    {
        $service = $event->service;
        $service->loadMissing('vendor.user');

        $userId = $service->vendor?->user_id;

        if ($userId === null) {
            return;
        }

        $context = [
            'service_name' => $service->getTranslation('name', 'en'),
            'rejection_reason' => $service->getTranslation('moderation_notes', 'en'),
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'service.rejected',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::System,
                userId: $userId,
                context: $context,
                referenceType: 'service',
                referenceId: $service->id,
            ));
        }
    }
}

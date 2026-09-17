<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\CustomerSuspended;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnCustomerSuspended implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(CustomerSuspended $event): void
    {
        $userId = $event->customer->id;

        foreach ([NotificationChannel::Email, NotificationChannel::InApp] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'customer.suspended',
                channel: $channel,
                audience: NotificationAudience::Customer,
                eventCategory: EventCategory::System,
                userId: $userId,
                context: [],
                referenceType: 'user',
                referenceId: $userId,
            ));
        }
    }
}

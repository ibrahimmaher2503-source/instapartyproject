<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\CustomerRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnCustomerWelcome implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(CustomerRegistered $event): void
    {
        $user = $event->user;

        $this->dispatcher->execute(new DispatchNotificationDTO(
            eventKey: 'customer.welcome',
            channel: NotificationChannel::Email,
            audience: NotificationAudience::Customer,
            eventCategory: EventCategory::System,
            userId: $user->id,
            context: [
                'first_name' => $user->first_name ?? $user->name,
            ],
            referenceType: 'user',
            referenceId: $user->id,
        ));
    }
}

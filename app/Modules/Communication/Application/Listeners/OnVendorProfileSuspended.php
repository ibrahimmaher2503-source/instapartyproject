<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\VendorSuspended;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnVendorProfileSuspended implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorSuspended $event): void
    {
        $vendorProfile = $event->vendorProfile;
        $vendorProfile->loadMissing('user');

        $userId = $vendorProfile->user_id;

        if ($userId === null) {
            return;
        }

        $context = [
            'vendor_name' => $vendorProfile->getTranslation('business_name', 'en'),
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'vendor.profile.suspended',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::System,
                userId: $userId,
                context: $context,
                referenceType: 'vendor_profile',
                referenceId: $vendorProfile->id,
            ));
        }
    }
}

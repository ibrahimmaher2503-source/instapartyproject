<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\VendorAutoSuspended;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnVendorProfileAutoSuspended implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorAutoSuspended $event): void
    {
        $vendorProfile = $event->vendorProfile;
        $vendorProfile->loadMissing('user');

        $userId = $vendorProfile->user_id;

        if ($userId === null) {
            return;
        }

        $context = [
            'vendor_name' => $vendorProfile->getTranslation('business_name', 'en'),
            'doc_type' => $event->document->doc_type->value,
            'expiry_date' => $event->document->expires_at?->format('Y-m-d') ?? '',
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email, NotificationChannel::Sms] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'vendor.profile.auto_suspended',
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

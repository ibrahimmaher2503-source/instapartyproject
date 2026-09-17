<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\VendorApprovedForType;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnVendorApprovedForType implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorApprovedForType $event): void
    {
        $approval = $event->approval;
        $approval->loadMissing('vendorProfile.user');

        $vendorProfile = $approval->vendorProfile;
        $userId = $vendorProfile?->user_id;

        if ($userId === null) {
            return;
        }

        $context = [
            'vendor_name' => $vendorProfile->getTranslation('business_name', 'en'),
            'product_type' => $approval->product_type->value,
            'phone_e164' => $vendorProfile->user?->phone_e164,
            'notification_type' => 'vendor_product_type_approved',
            'action_url' => '/vendor/services',
            'data' => [
                'entity_type' => 'vendor',
                'entity_id' => (string) $vendorProfile->public_id,
                'product_type' => $approval->product_type->value,
            ],
        ];

        foreach ([NotificationChannel::InApp, NotificationChannel::Push, NotificationChannel::Sms, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'vendor.product_type.approved',
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

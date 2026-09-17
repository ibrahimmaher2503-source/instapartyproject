<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\VendorTypeRevoked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class OnVendorTypeRevoked implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorTypeRevoked $event): void
    {
        $userId = DB::table('vendor_profiles')
            ->where('id', $event->vendorProfileId)
            ->value('user_id');

        if ($userId === null) {
            return;
        }

        $context = [
            'product_type' => $event->productType->value,
            'reason' => $event->reason['en'] ?? '',
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'vendor.product_type.revoked',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::System,
                userId: $userId,
                context: $context,
                referenceType: 'vendor_profile',
                referenceId: $event->vendorProfileId,
            ));
        }
    }
}

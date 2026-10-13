<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\VendorDocumentApproved;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnVendorDocumentApproved implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorDocumentApproved $event): void
    {
        $document = $event->document;
        $document->loadMissing('vendorProfile.user');

        $userId = $document->vendorProfile?->user_id;

        if ($userId === null) {
            return;
        }

        $context = [
            'doc_type' => $document->doc_type->value,
        ];

        foreach ([NotificationChannel::InApp, NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $channelContext = $channel === NotificationChannel::InApp
                ? [...$context, 'action_url' => route('filament.vendor.pages.vendor-documents-page')]
                : $context;

            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'vendor.document.approved',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::System,
                userId: $userId,
                context: $channelContext,
                referenceType: 'vendor_document',
                referenceId: $document->id,
            ));
        }
    }
}

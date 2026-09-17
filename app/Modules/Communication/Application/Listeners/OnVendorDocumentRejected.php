<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Identity\Domain\Events\VendorDocumentRejected;
use Illuminate\Contracts\Queue\ShouldQueue;

class OnVendorDocumentRejected implements ShouldQueue
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatcher,
    ) {}

    public function handle(VendorDocumentRejected $event): void
    {
        $document = $event->document;
        $document->loadMissing('vendorProfile.user');

        $userId = $document->vendorProfile?->user_id;

        if ($userId === null) {
            return;
        }

        $context = [
            'doc_type' => $document->doc_type->value,
            'review_notes' => $event->reviewNotes['en'] ?? (is_string($event->reviewNotes[0] ?? null) ? $event->reviewNotes[0] : ''),
        ];

        foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
            $this->dispatcher->execute(new DispatchNotificationDTO(
                eventKey: 'vendor.document.rejected',
                channel: $channel,
                audience: NotificationAudience::Vendor,
                eventCategory: EventCategory::System,
                userId: $userId,
                context: $context,
                referenceType: 'vendor_document',
                referenceId: $document->id,
            ));
        }
    }
}

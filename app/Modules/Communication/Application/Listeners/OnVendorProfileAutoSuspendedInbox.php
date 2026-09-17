<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Identity\Domain\Events\VendorAutoSuspended;

class OnVendorProfileAutoSuspendedInbox
{
    public function __construct(
        private readonly RouteToAdminInboxAction $router,
    ) {}

    public function handle(VendorAutoSuspended $event): void
    {
        $vendorProfile = $event->vendorProfile;
        $docType = $event->document->doc_type->value;
        $vendorName = $vendorProfile->getTranslation('business_name', 'en');
        $expiryDate = $event->document->expires_at?->format('Y-m-d') ?? 'unknown';

        $this->router->execute(
            eventKey: 'vendor.auto_suspended',
            severity: AdminInboxSeverity::Critical,
            sourceType: 'vendor_profile',
            sourceId: $vendorProfile->id,
            title: [
                'en' => "[AUTO-SUSPENDED] {$vendorName} — {$docType} expired",
                'ar' => "[إيقاف تلقائي] {$vendorName} — انتهت صلاحية {$docType}",
            ],
            body: [
                'en' => "Vendor {$vendorName} was auto-suspended. Document {$docType} expired on {$expiryDate}. All active services archived.",
                'ar' => "تم إيقاف المورد {$vendorName} تلقائياً. انتهت صلاحية المستند {$docType} في {$expiryDate}. تم أرشفة جميع الخدمات النشطة.",
            ],
        );
    }
}

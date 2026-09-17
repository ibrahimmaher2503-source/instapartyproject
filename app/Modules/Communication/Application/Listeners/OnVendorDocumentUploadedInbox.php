<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Communication\Application\Actions\RouteToAdminInboxAction;
use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Identity\Domain\Events\VendorDocumentUploaded;

class OnVendorDocumentUploadedInbox
{
    public function __construct(
        private readonly RouteToAdminInboxAction $router,
    ) {}

    public function handle(VendorDocumentUploaded $event): void
    {
        $document = $event->document;
        $document->loadMissing('vendorProfile');

        $vendorId = $document->vendor_profile_id;
        $vendorName = $document->vendorProfile?->getTranslation('business_name', 'en') ?? "Vendor #{$vendorId}";
        $docType = $document->doc_type->value;

        $this->router->execute(
            eventKey: 'vendor.document.uploaded',
            severity: AdminInboxSeverity::Info,
            sourceType: 'vendor_document',
            sourceId: $document->id,
            title: [
                'en' => "New document uploaded by {$vendorName}",
                'ar' => "تم رفع مستند جديد من {$vendorName}",
            ],
            body: [
                'en' => "Vendor {$vendorName} uploaded a {$docType} document awaiting review.",
                'ar' => "رفع المورد {$vendorName} مستند {$docType} في انتظار المراجعة.",
            ],
        );
    }
}

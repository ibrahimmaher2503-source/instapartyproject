<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\ComplianceEventType;
use App\Modules\Identity\Domain\Events\VendorDocumentExpired;
use App\Modules\Identity\Domain\Models\VendorComplianceEvent;
use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecordExpiredVendorDocumentAction
{
    public function execute(VendorDocument $document): bool
    {
        return DB::transaction(function () use ($document): bool {
            $locked = VendorDocument::query()->with('vendorProfile')->lockForUpdate()->findOrFail($document->getKey());

            if (VendorComplianceEvent::query()
                ->where('document_id', $locked->getKey())
                ->where('event_type', ComplianceEventType::Expired->value)
                ->exists()) {
                return false;
            }

            VendorComplianceEvent::query()->create([
                'public_id' => (string) Str::ulid(),
                'vendor_profile_id' => $locked->vendor_profile_id,
                'document_id' => $locked->getKey(),
                'event_type' => ComplianceEventType::Expired,
                'occurred_at' => now(),
                'reason' => [
                    'en' => __('identity::identity.compliance.document_expired', ['date' => $locked->expires_at->format('Y-m-d')], 'en'),
                    'ar' => __('identity::identity.compliance.document_expired', ['date' => $locked->expires_at->format('Y-m-d')], 'ar'),
                ],
            ]);

            DB::afterCommit(fn () => event(new VendorDocumentExpired($locked->vendorProfile, $locked)));

            return true;
        }, 3);
    }
}

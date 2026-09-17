<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver;
use App\Modules\Identity\Domain\Enums\ComplianceEventType;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Events\VendorAutoSuspended;
use App\Modules\Identity\Domain\Models\VendorComplianceEvent;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AutoSuspendForExpiredDocAction
{
    public function __construct(
        private readonly RequiredVendorDocumentTypesResolver $requiredDocuments,
    ) {}

    public function execute(VendorDocument $document): VendorProfile
    {
        return DB::transaction(function () use ($document): VendorProfile {
            $lockedDocument = VendorDocument::query()->lockForUpdate()->findOrFail($document->getKey());
            $vendorProfile = VendorProfile::query()->lockForUpdate()->findOrFail($lockedDocument->vendor_profile_id);

            $latestId = VendorDocument::query()
                ->where('vendor_profile_id', $vendorProfile->getKey())
                ->where('doc_type', $lockedDocument->doc_type->value)
                ->max('id');
            $requiredTypes = $this->requiredDocuments->forBusinessType($vendorProfile->business_type);
            $today = CarbonImmutable::today((string) config('app.timezone', 'UTC'));

            $qualifies = $latestId === $lockedDocument->getKey()
                && in_array($lockedDocument->doc_type->value, $requiredTypes, true)
                && $lockedDocument->status === DocumentStatus::Approved
                && $lockedDocument->is_critical
                && $lockedDocument->expires_at !== null
                && $lockedDocument->expires_at->toDateString() < $today->toDateString();

            if (! $qualifies || $vendorProfile->approval_status instanceof SuspendedState) {
                return $vendorProfile;
            }

            if (! ($vendorProfile->approval_status instanceof ApprovedState)) {
                return $vendorProfile;
            }

            $expiryDate = $lockedDocument->expires_at->format('Y-m-d');
            $reason = [
                'en' => __('identity::identity.compliance.auto_suspended', ['date' => $expiryDate], 'en'),
                'ar' => __('identity::identity.compliance.auto_suspended', ['date' => $expiryDate], 'ar'),
            ];

            $vendorProfile->approval_status->transitionTo(
                SuspendedState::class,
                null,
                $reason['en'],
                true,
                false,
            );

            $alreadyRecorded = VendorComplianceEvent::query()
                ->where('document_id', $lockedDocument->getKey())
                ->where('event_type', ComplianceEventType::AutoSuspended->value)
                ->exists();

            if (! $alreadyRecorded) {
                VendorComplianceEvent::query()->create([
                    'public_id' => (string) Str::ulid(),
                    'vendor_profile_id' => $vendorProfile->getKey(),
                    'document_id' => $lockedDocument->getKey(),
                    'event_type' => ComplianceEventType::AutoSuspended,
                    'admin_id' => null,
                    'occurred_at' => now(),
                    'reason' => $reason,
                ]);

                DB::afterCommit(fn () => event(new VendorAutoSuspended($vendorProfile, $lockedDocument)));
            }

            return $vendorProfile->refresh();
        }, 3);
    }
}

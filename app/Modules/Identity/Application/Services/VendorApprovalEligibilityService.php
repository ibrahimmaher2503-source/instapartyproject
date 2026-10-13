<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Application\DTOs\VendorApprovalEligibilityResult;
use App\Modules\Identity\Application\Support\MaskBankData;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Carbon\CarbonImmutable;

final class VendorApprovalEligibilityService
{
    public function __construct(
        private readonly RequiredVendorDocumentTypesResolver $documentTypesResolver,
    ) {}

    public function evaluate(VendorProfile $vendor, ?CarbonImmutable $asOf = null): VendorApprovalEligibilityResult
    {
        $asOf ??= CarbonImmutable::now(config('app.timezone', 'UTC'));
        $vendor->loadMissing('user');

        $businessType = $vendor->business_type instanceof BusinessType
            ? $vendor->business_type
            : BusinessType::tryFrom((string) $vendor->business_type);
        $documentIssues = $businessType === null
            ? null
            : $this->requiredDocumentIssues($vendor, $businessType, $asOf);

        $checks = [
            'identity' => filled($vendor->user?->name),
            'contact' => filled($vendor->user?->email)
                && filled($vendor->user?->phone_e164)
                && $vendor->user?->email_verified_at !== null,
            'profile' => $this->hasRequiredBusinessFields($vendor, $businessType),
            'geography' => filled($vendor->primary_governorate_id)
                && filled($vendor->primary_city_id)
                && $vendor->primaryCity()
                    ->where('governorate_id', $vendor->primary_governorate_id)
                    ->exists(),
            'banking' => ! MaskBankData::isAbsent($vendor->bank_name)
                && ! MaskBankData::isAbsent($vendor->bank_account_holder)
                && ! MaskBankData::isAbsent($vendor->bank_iban),
            'hours' => $vendor->businessHours()->exists(),
            'coverage' => $vendor->coverageAreas()->exists(),
            'documents' => $documentIssues !== null && $documentIssues === [],
        ];

        $errors = [];
        foreach ($checks as $check => $passed) {
            if (! $passed) {
                $errors[$check] = match (true) {
                    $check === 'documents' && $documentIssues === null => __(
                        'identity::identity.approval_eligibility.documents_business_type_missing'
                    ),
                    $check === 'documents' => __(
                        'identity::identity.approval_eligibility.documents_need_attention',
                        ['documents' => implode('; ', $documentIssues ?? [])],
                    ),
                    default => __("identity::identity.approval_eligibility.{$check}"),
                };
            }
        }

        return new VendorApprovalEligibilityResult(
            eligible: ! in_array(false, $checks, true),
            checks: $checks,
            errors: $errors,
        );
    }

    private function hasRequiredBusinessFields(VendorProfile $vendor, ?BusinessType $businessType): bool
    {
        if (
            ! $businessType
            || blank($vendor->getTranslation('business_name', 'en', false))
            || blank($vendor->getTranslation('business_name', 'ar', false))
            || blank($vendor->getTranslation('address_line', 'en', false))
            || blank($vendor->getTranslation('address_line', 'ar', false))
        ) {
            return false;
        }

        return match ($businessType) {
            BusinessType::Individual => filled($vendor->national_id),
            BusinessType::Company => filled($vendor->commercial_register_no) && filled($vendor->tax_id),
            BusinessType::Establishment => filled($vendor->commercial_register_no),
        };
    }

    /** @return array<int, string> */
    private function requiredDocumentIssues(
        VendorProfile $vendor,
        BusinessType $businessType,
        CarbonImmutable $asOf,
    ): array {
        $requiredTypes = $this->documentTypesResolver->forBusinessType($businessType->value);
        $latest = $vendor->documents()
            ->whereIn('doc_type', $requiredTypes)
            ->latestPerType()
            ->get()
            ->keyBy(fn (VendorDocument $document): string => $document->doc_type instanceof DocumentType
                ? $document->doc_type->value
                : (string) $document->doc_type);
        $issues = [];

        foreach ($requiredTypes as $type) {
            $document = $latest->get($type);
            $status = null;

            if ($document === null) {
                $status = 'missing';
            } elseif ($document->status !== DocumentStatus::Approved) {
                $status = $document->status instanceof DocumentStatus
                    ? $document->status->value
                    : (string) $document->status;
            } elseif ($document->expires_at !== null) {
                $expiresAt = CarbonImmutable::parse(
                    $document->expires_at->toDateString(),
                    config('app.timezone', 'UTC'),
                )->endOfDay();

                if ($expiresAt->lessThan($asOf->setTimezone(config('app.timezone', 'UTC')))) {
                    $status = 'expired';
                }
            }

            if ($status !== null) {
                $issues[] = __('identity::identity.approval_eligibility.document_issue', [
                    'document' => __("identity::identity.document_type.{$type}"),
                    'status' => __("identity::identity.approval_eligibility.document_status.{$status}"),
                ]);
            }
        }

        return $issues;
    }
}

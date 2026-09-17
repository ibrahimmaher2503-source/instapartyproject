<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Application\DTOs\VendorApprovalEligibilityResult;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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

        $checks = [
            'identity' => filled($vendor->user?->name),
            'contact' => filled($vendor->user?->email)
                && filled($vendor->user?->phone_e164)
                && $vendor->user?->email_verified_at !== null
                && $vendor->user?->phone_verified_at !== null,
            'profile' => $this->hasRequiredBusinessFields($vendor, $businessType),
            'geography' => filled($vendor->primary_governorate_id)
                && filled($vendor->primary_city_id)
                && $vendor->primaryCity()
                    ->where('governorate_id', $vendor->primary_governorate_id)
                    ->exists(),
            'banking' => filled($vendor->bank_name)
                && filled($vendor->bank_account_holder)
                && filled($vendor->bank_iban),
            'hours' => $vendor->businessHours()->exists(),
            'coverage' => $vendor->coverageAreas()->exists(),
            'documents' => $this->hasValidRequiredDocuments($vendor, $businessType, $asOf),
        ];

        $errors = [];
        foreach ($checks as $check => $passed) {
            if (! $passed) {
                $errors[$check] = __("identity::identity.approval_eligibility.{$check}");
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

    private function hasValidRequiredDocuments(
        VendorProfile $vendor,
        ?BusinessType $businessType,
        CarbonImmutable $asOf,
    ): bool {
        if (! $businessType) {
            return false;
        }

        $requiredTypes = $this->documentTypesResolver->forBusinessType($businessType->value);
        $latest = $vendor->documents()
            ->whereIn('doc_type', $requiredTypes)
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->groupBy(fn ($document): string => $document->doc_type instanceof DocumentType
                ? $document->doc_type->value
                : (string) $document->doc_type)
            ->map(fn (Collection $documents) => $documents->first());

        return collect($requiredTypes)->every(function (string $type) use ($latest, $asOf): bool {
            $document = $latest->get($type);

            if (! $document || $document->status !== DocumentStatus::Approved) {
                return false;
            }

            if ($document->expires_at === null) {
                return true;
            }

            $expiresAt = CarbonImmutable::parse(
                $document->expires_at->toDateString(),
                config('app.timezone', 'UTC'),
            )->endOfDay();

            return $expiresAt->greaterThanOrEqualTo($asOf->setTimezone(config('app.timezone', 'UTC')));
        });
    }
}

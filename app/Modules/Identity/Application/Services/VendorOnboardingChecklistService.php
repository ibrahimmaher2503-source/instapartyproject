<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistDTO;
use App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistItemDTO;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Enums\ChecklistItemStatus;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Enums\RejectionState;
use App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Filament\Vendor\Pages\VendorApprovalStatusPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorBusinessHoursPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorCoverageAreasPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\ModelStates\State;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class VendorOnboardingChecklistService
{
    /** Priority order for nextRecommendedAction */
    private const PRIORITY_KEYS = [
        VendorOnboardingChecklistItemKey::Profile,
        VendorOnboardingChecklistItemKey::DocsUploaded,
        VendorOnboardingChecklistItemKey::Banking,
        VendorOnboardingChecklistItemKey::Coverage,
        VendorOnboardingChecklistItemKey::Hours,
        VendorOnboardingChecklistItemKey::ApprovedTypes,
        VendorOnboardingChecklistItemKey::ServiceDrafted,
        VendorOnboardingChecklistItemKey::ServiceSubmitted,
    ];

    public function __construct(
        private readonly RequiredVendorDocumentTypesResolver $documentTypesResolver,
        private readonly VendorServicePresenceQuery $servicePresenceQuery,
        private readonly VendorApprovalEligibilityService $approvalEligibility,
    ) {}

    public function forVendor(VendorProfile $vendor): VendorOnboardingChecklistDTO
    {
        $approvalEligibility = $this->approvalEligibility->evaluate($vendor);
        $approvalStatusValue = $vendor->approval_status instanceof State
            ? $vendor->approval_status->getMorphClass()
            : (string) $vendor->approval_status;

        if ($approvalStatusValue === 'suspended') {
            return $this->buildSuspendedDTO($vendor);
        }

        // Load documents once for both docs-uploaded and docs-approved checks (query budget = 1)
        $requiredTypes = $this->documentTypesResolver->forBusinessType(
            $vendor->business_type instanceof BusinessType
                ? $vendor->business_type->value
                : (string) $vendor->business_type
        );

        $latestDocsByType = $vendor->documents()
            ->whereIn('doc_type', $requiredTypes)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy(fn ($document): string => $document->doc_type instanceof DocumentType
                ? $document->doc_type->value
                : (string) $document->doc_type)
            ->map(fn (Collection $docs) => $docs->first());

        $approvedTypeRows = $vendor->approvedTypes()->get();

        $items = [
            $this->profileItem($vendor, $approvalEligibility->passes('identity') && $approvalEligibility->passes('contact') && $approvalEligibility->passes('profile') && $approvalEligibility->passes('geography')),
            $this->bankingItem($vendor, $approvalEligibility->passes('banking')),
            $this->docsUploadedItem($vendor, $requiredTypes, $latestDocsByType),
            $this->docsApprovedItem($vendor, $requiredTypes, $latestDocsByType, $approvalEligibility->passes('documents')),
            $this->coverageItem($vendor, $approvalEligibility->passes('coverage')),
            $this->hoursItem($vendor, $approvalEligibility->passes('hours')),
            $this->serviceDraftedItem($vendor, $approvedTypeRows),
            $this->serviceSubmittedItem($vendor, $approvedTypeRows),
            $this->approvalStatusItem($vendor, $approvalStatusValue),
            $this->approvedTypesItem($approvedTypeRows, $vendor->approval_status instanceof ApprovedState),
        ];

        $completedCount = count(array_filter(
            $items,
            fn (VendorOnboardingChecklistItemDTO $item) => $item->status === ChecklistItemStatus::Complete
        ));

        $itemsByKey = collect($items)->keyBy(fn ($item) => $item->key->value);

        $nextRecommendedAction = null;
        foreach (self::PRIORITY_KEYS as $key) {
            $item = $itemsByKey->get($key->value);
            if ($item && $item->status !== ChecklistItemStatus::Complete && $item->url !== null) {
                $nextRecommendedAction = $item;
                break;
            }
        }

        [$rejectionState, $rejectionReason] = $this->rejectionBannerFields($vendor, $approvalStatusValue);

        $approvedProductTypes = $approvedTypeRows->map(
            fn ($row) => $row->product_type instanceof ProductType
                ? $row->product_type
                : ProductType::from((string) $row->product_type)
        )->values()->all();

        return new VendorOnboardingChecklistDTO(
            vendorId: $vendor->id,
            isSuspended: false,
            suspendedAt: null,
            suspensionReason: null,
            rejectionState: $rejectionState,
            rejectionReason: $rejectionReason,
            items: $items,
            completedCount: $completedCount,
            totalCount: 10,
            progressPercent: intdiv($completedCount * 100, 10),
            nextRecommendedAction: $nextRecommendedAction,
            approvedProductTypes: $approvedProductTypes,
        );
    }

    private function buildSuspendedDTO(VendorProfile $vendor): VendorOnboardingChecklistDTO
    {
        $suspendedAt = $vendor->suspended_at
            ? CarbonImmutable::instance(Carbon::parse($vendor->suspended_at))
            : null;

        $suspensionReason = $vendor->getTranslation('suspension_reason', app()->getLocale(), false)
            ?: $vendor->getTranslation('rejection_reason', app()->getLocale(), false);

        return new VendorOnboardingChecklistDTO(
            vendorId: $vendor->id,
            isSuspended: true,
            suspendedAt: $suspendedAt,
            suspensionReason: $suspensionReason ?: null,
            rejectionState: RejectionState::None,
            rejectionReason: null,
            items: [],
            completedCount: 0,
            totalCount: 10,
            progressPercent: 0,
            nextRecommendedAction: null,
            approvedProductTypes: [],
        );
    }

    /** Resolves a Filament page/resource URL; returns null in test environments where panel routes are unregistered. */
    private function safeUrl(callable $resolver): ?string
    {
        try {
            return $resolver();
        } catch (RouteNotFoundException|UrlGenerationException) {
            return null;
        }
    }

    // T038
    private function profileItem(VendorProfile $vendor, bool $complete): VendorOnboardingChecklistItemDTO
    {

        $url = $complete ? null : $this->safeUrl(fn () => VendorProfilePage::getUrl());

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::Profile,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.profile.label'),
            subText: null,
            url: $url,
        );
    }

    // T039
    private function bankingItem(VendorProfile $vendor, bool $complete): VendorOnboardingChecklistItemDTO
    {

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::Banking,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.banking.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorProfilePage::getUrl()),
        );
    }

    // T040
    private function docsUploadedItem(VendorProfile $vendor, array $requiredTypes, Collection $latestDocsByType): VendorOnboardingChecklistItemDTO
    {
        $complete = collect($requiredTypes)->every(fn (string $type) => $latestDocsByType->has($type));

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::DocsUploaded,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.docs_uploaded.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorDocumentsPage::getUrl()),
        );
    }

    // T041 — reuses already-loaded $latestDocsByType (query budget stays at 1 for both docs checks)
    private function docsApprovedItem(VendorProfile $vendor, array $requiredTypes, Collection $latestDocsByType, bool $eligible): VendorOnboardingChecklistItemDTO
    {
        $now = Carbon::now();
        $statuses = collect($requiredTypes)->map(function (string $type) use ($latestDocsByType, $now): ChecklistItemStatus {
            $document = $latestDocsByType->get($type);

            if ($document === null) {
                return ChecklistItemStatus::Pending;
            }

            return match ($document->status) {
                DocumentStatus::Rejected => ChecklistItemStatus::Danger,
                DocumentStatus::Pending => ChecklistItemStatus::Info,
                DocumentStatus::Approved => $document->expires_at !== null
                    && Carbon::parse($document->expires_at->toDateString(), config('app.timezone', 'UTC'))->endOfDay()->lt($now)
                        ? ChecklistItemStatus::Warning
                        : ChecklistItemStatus::Complete,
            };
        });

        $status = $eligible ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending;
        foreach ([ChecklistItemStatus::Danger, ChecklistItemStatus::Warning, ChecklistItemStatus::Pending, ChecklistItemStatus::Info] as $candidate) {
            if ($statuses->contains($candidate)) {
                $status = $candidate;
                break;
            }
        }

        $url = $status === ChecklistItemStatus::Complete ? null : $this->safeUrl(fn () => VendorDocumentsPage::getUrl());

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::DocsApproved,
            status: $status,
            label: __('identity::vendor-onboarding.rows.docs_approved.label'),
            subText: null,
            url: $url,
        );
    }

    // T042
    private function coverageItem(VendorProfile $vendor, bool $complete): VendorOnboardingChecklistItemDTO
    {

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::Coverage,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.coverage.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorCoverageAreasPage::getUrl()),
        );
    }

    // T043
    private function hoursItem(VendorProfile $vendor, bool $complete): VendorOnboardingChecklistItemDTO
    {

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::Hours,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.hours.label'),
            subText: null,
            url: $complete ? null : $this->safeUrl(fn () => VendorBusinessHoursPage::getUrl()),
        );
    }

    // T044
    private function serviceDraftedItem(VendorProfile $vendor, Collection $approvedTypeRows): VendorOnboardingChecklistItemDTO
    {
        $complete = $this->servicePresenceQuery->hasAnyService($vendor->id);
        $canManageServices = $vendor->approval_status instanceof ApprovedState && $this->hasEnabledApprovedType($approvedTypeRows);

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::ServiceDrafted,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.service_drafted.label'),
            subText: null,
            url: $complete || ! $canManageServices ? null : $this->firstApprovedProductTypeCreateUrl($approvedTypeRows),
        );
    }

    private function firstApprovedProductTypeCreateUrl(Collection $approvedTypeRows): ?string
    {
        $approvedTypes = $approvedTypeRows->map(fn ($row): ProductType => $row->product_type instanceof ProductType
            ? $row->product_type
            : ProductType::from((string) $row->product_type));

        foreach ($this->enabledProductTypes() as $type) {
            if (! $approvedTypes->contains($type)) {
                continue;
            }

            $url = match ($type) {
                ProductType::Rental => VendorRentalServiceResource::canAccess()
                    ? $this->safeUrl(fn () => VendorRentalServiceResource::getUrl('create', panel: 'vendor'))
                    : null,
                ProductType::Sale => VendorSaleServiceResource::canAccess()
                    ? $this->safeUrl(fn () => VendorSaleServiceResource::getUrl('create', panel: 'vendor'))
                    : null,
                ProductType::Digital => VendorDigitalServiceResource::canAccess()
                    ? $this->safeUrl(fn () => VendorDigitalServiceResource::getUrl('create', panel: 'vendor'))
                    : null,
            };

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function serviceSubmittedItem(VendorProfile $vendor, Collection $approvedTypeRows): VendorOnboardingChecklistItemDTO
    {
        $complete = $this->servicePresenceQuery->hasServiceInReviewOrPublished($vendor->id);
        $canManageServices = $vendor->approval_status instanceof ApprovedState && $this->hasEnabledApprovedType($approvedTypeRows);

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::ServiceSubmitted,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.service_submitted.label'),
            subText: null,
            url: $complete || ! $canManageServices ? null : $this->safeUrl(fn () => VendorServicesListPage::getUrl(panel: 'vendor')),
        );
    }

    // T045
    private function approvalStatusItem(VendorProfile $vendor, string $approvalStatusValue): VendorOnboardingChecklistItemDTO
    {
        $status = match ($approvalStatusValue) {
            'approved' => ChecklistItemStatus::Complete,
            'pending' => ChecklistItemStatus::Info,
            'changes_requested' => ChecklistItemStatus::Warning,
            'rejected' => ChecklistItemStatus::Danger,
            default => ChecklistItemStatus::Pending,
        };

        $subText = __('identity::vendor-onboarding.approval_status.'.$approvalStatusValue);

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::ApprovalStatus,
            status: $status,
            label: __('identity::vendor-onboarding.rows.approval_status.label'),
            subText: $subText,
            url: null,
        );
    }

    // T046
    private function approvedTypesItem(Collection $approvedTypeRows, bool $accountApproved): VendorOnboardingChecklistItemDTO
    {
        $approvedTypes = $approvedTypeRows->map(fn ($row): ProductType => $row->product_type instanceof ProductType
            ? $row->product_type
            : ProductType::from((string) $row->product_type));
        $approvedTypeValues = $approvedTypes->map(fn (ProductType $type): string => $type->value)->unique()->values();
        $enabledTypes = $this->enabledProductTypes();
        $complete = $enabledTypes->contains(fn (ProductType $type): bool => $approvedTypeValues->contains($type->value));
        $typeStatuses = $enabledTypes->map(fn (ProductType $type): string => __('identity::vendor-onboarding.approved_types.status_line', [
            'type' => $type->label(),
            'status' => __('identity::vendor-onboarding.approved_types.'.($approvedTypeValues->contains($type->value) ? 'approved' : 'pending')),
        ]))->implode(' · ');
        $subText = __('identity::vendor-onboarding.approved_types.status_summary', ['statuses' => $typeStatuses])
            .' '.__('identity::vendor-onboarding.approved_types.creation_guidance');
        if (! (bool) config('catalog.digital_services_enabled', true)) {
            $subText .= ' '.__('identity::vendor-onboarding.approved_types.digital_unavailable');
        }
        $hasPendingTypes = $enabledTypes->contains(fn (ProductType $type): bool => ! $approvedTypeValues->contains($type->value));

        return new VendorOnboardingChecklistItemDTO(
            key: VendorOnboardingChecklistItemKey::ApprovedTypes,
            status: $complete ? ChecklistItemStatus::Complete : ChecklistItemStatus::Pending,
            label: __('identity::vendor-onboarding.rows.approved_types.label'),
            subText: $subText,
            url: $accountApproved && $hasPendingTypes
                ? $this->safeUrl(fn () => VendorApprovalStatusPage::getUrl(panel: 'vendor'))
                : null,
        );
    }

    /** @return Collection<int, ProductType> */
    private function enabledProductTypes(): Collection
    {
        return collect(ProductType::cases())
            ->filter(fn (ProductType $type): bool => $type !== ProductType::Digital || (bool) config('catalog.digital_services_enabled', true))
            ->values();
    }

    private function hasEnabledApprovedType(Collection $approvedTypeRows): bool
    {
        $approvedTypeValues = $approvedTypeRows->map(fn ($row): string => $row->product_type instanceof ProductType
            ? $row->product_type->value
            : (string) $row->product_type);

        return $this->enabledProductTypes()->contains(fn (ProductType $type): bool => $approvedTypeValues->contains($type->value));
    }

    // T054 (US2) — rejection banner fields
    private function rejectionBannerFields(VendorProfile $vendor, string $approvalStatusValue): array
    {
        $rejectionState = match ($approvalStatusValue) {
            'rejected' => RejectionState::Rejected,
            'changes_requested' => RejectionState::ChangesRequested,
            default => RejectionState::None,
        };

        $rejectionReason = $rejectionState !== RejectionState::None
            ? ($vendor->getTranslation('rejection_reason', app()->getLocale(), false) ?: null)
            : null;

        return [$rejectionState, $rejectionReason];
    }
}

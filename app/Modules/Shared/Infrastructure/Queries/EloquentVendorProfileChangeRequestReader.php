<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Queries;

use App\Modules\Shared\Application\DTOs\VendorProfileChangeRequestData;
use App\Modules\Shared\Application\DTOs\VendorProfileChangeRequestItemData;
use App\Modules\Shared\Domain\Contracts\VendorProfileChangeRequestReader;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Enums\ChangeRequestSubjectType;
use App\Modules\Shared\Domain\Models\ChangeRequest;

final class EloquentVendorProfileChangeRequestReader implements VendorProfileChangeRequestReader
{
    public function findCurrentOpenForVendorProfile(int $vendorProfileId): ?VendorProfileChangeRequestData
    {
        $request = ChangeRequest::query()
            ->where('subject_type', ChangeRequestSubjectType::VendorProfile->value)
            ->where('subject_id', $vendorProfileId)
            ->where('status', ChangeRequestStatus::Open->value)
            ->with('items')
            ->orderByDesc('cycle_number')
            ->first();

        if ($request === null) {
            return null;
        }

        return new VendorProfileChangeRequestData(
            cycleNumber: $request->cycle_number,
            items: $request->items
                ->map(fn ($item): VendorProfileChangeRequestItemData => new VendorProfileChangeRequestItemData(
                    publicId: $item->public_id,
                    fieldPath: $item->field_path,
                    requestedChangeEn: $item->requested_change_en,
                    requestedChangeAr: $item->requested_change_ar,
                ))
                ->all(),
        );
    }
}

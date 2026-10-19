<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocumentType;
use Illuminate\Support\Facades\DB;

final class DeleteVendorDocumentTypeAction
{
    public function execute(VendorDocumentType $type, ?User $actor = null): bool
    {
        $actor ??= auth()->user();
        abort_unless($actor?->can('review_vendor_documents'), 403);

        return DB::transaction(function () use ($type, $actor): bool {
            $record = VendorDocumentType::query()->lockForUpdate()->findOrFail($type->getKey());
            $deleted = $record->update(['is_active' => false, 'required_for' => []]);
            activity()->on($record)->causedBy($actor)->log('removed_vendor_document_type');

            return $deleted;
        });
    }
}

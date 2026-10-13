<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteVendorDocumentAction
{
    public function execute(VendorProfile $vendorProfile, VendorDocument $document): void
    {
        DB::transaction(function () use ($vendorProfile, $document): void {
            $locked = VendorDocument::query()->lockForUpdate()->findOrFail($document->getKey());
            if ($locked->vendor_profile_id !== $vendorProfile->id) {
                throw ValidationException::withMessages([
                    'document' => __('identity.errors.document_not_owned'),
                ]);
            }
            if (! in_array($locked->status, [DocumentStatus::Pending, DocumentStatus::Rejected], true)) {
                throw ValidationException::withMessages([
                    'document' => __('identity.errors.document_not_deletable'),
                ]);
            }

            $path = $locked->file_path;
            $diskName = config('filesystems.disks.s3-private.key')
                ? 's3-private'
                : config('filesystems.default', 'local');
            $locked->delete();

            DB::afterCommit(fn () => Storage::disk($diskName)->delete($path));
        }, 3);
    }
}

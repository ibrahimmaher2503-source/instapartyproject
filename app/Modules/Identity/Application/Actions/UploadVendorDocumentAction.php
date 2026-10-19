<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Events\VendorDocumentUploaded;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class UploadVendorDocumentAction
{
    public function execute(VendorProfile $vendorProfile, UploadedFile $file, DocumentType|string $docType): VendorDocument
    {
        $docType = $docType instanceof DocumentType ? $docType->value : $docType;
        Validator::make(['file' => $file, 'doc_type' => $docType], [
            'doc_type' => ['required', Rule::in(array_keys(app(RequiredVendorDocumentTypesResolver::class)->options()))],
            'file' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf,image/jpeg,image/png', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'doc_type.in' => __('identity::document_types.invalid_type'),
            'file.mimetypes' => __('identity::identity.validation.file_mime_invalid'),
            'file.mimes' => __('identity::identity.validation.file_mime_invalid'),
        ])->validate();

        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $storedName = (string) Str::ulid().'.'.$extension;
        $directory = "vendors/{$vendorProfile->id}/documents";
        $diskName = config('filesystems.disks.s3-private.key')
            ? 's3-private'
            : config('filesystems.default', 'local');
        $path = $file->storeAs($directory, $storedName, $diskName);

        if ($path === false) {
            throw new RuntimeException('Failed to store vendor document.');
        }

        try {
            $document = DB::transaction(function () use ($vendorProfile, $docType, $path, $file): VendorDocument {
                $doc = VendorDocument::create([
                    'vendor_profile_id' => $vendorProfile->id,
                    'doc_type' => $docType,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'status' => DocumentStatus::Pending->value,
                ]);

                DB::afterCommit(fn () => event(new VendorDocumentUploaded($doc)));

                return $doc;
            });
        } catch (Throwable $e) {
            Storage::disk($diskName)->delete($path);
            throw $e;
        }

        return $document;
    }
}

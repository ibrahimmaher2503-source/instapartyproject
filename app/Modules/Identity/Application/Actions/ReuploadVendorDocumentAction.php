<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Events\VendorDocumentUploaded;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class ReuploadVendorDocumentAction
{
    public function execute(
        VendorDocument $document,
        UploadedFile $file,
        ?User $actor = null,
    ): VendorDocument {
        $actor ??= auth()->user();
        $ownsDocument = $actor?->vendorProfile?->getKey() === $document->vendor_profile_id;
        abort_unless($actor !== null && ($ownsDocument || $actor->can('review_vendor_documents')), 403);

        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $diskName = config('filesystems.disks.s3-private.key') ? 's3-private' : config('filesystems.default', 'local');
        $directory = "vendors/{$document->vendorProfile->public_id}/documents";
        $path = $file->storeAs($directory, (string) Str::ulid().'.'.$extension, $diskName);

        if ($path === false) {
            throw new RuntimeException('Failed to store vendor document.');
        }

        try {
            return DB::transaction(function () use ($document, $path, $file, $actor): VendorDocument {
                $locked = VendorDocument::query()->lockForUpdate()->findOrFail($document->getKey());

                if ($locked->status !== DocumentStatus::Rejected) {
                    throw new ConflictHttpException(__('identity::identity.validation.document_not_reuploadable'));
                }

                $pendingReplacementExists = VendorDocument::query()
                    ->where('vendor_profile_id', $locked->vendor_profile_id)
                    ->where('doc_type', $locked->doc_type->value)
                    ->where('status', DocumentStatus::Pending->value)
                    ->where('id', '>', $locked->getKey())
                    ->exists();

                if ($pendingReplacementExists) {
                    throw new ConflictHttpException(__('identity::identity.validation.document_already_pending'));
                }

                $replacement = VendorDocument::query()->create([
                    'vendor_profile_id' => $locked->vendor_profile_id,
                    'doc_type' => $locked->doc_type->value,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'status' => DocumentStatus::Pending->value,
                    'expires_at' => $locked->expires_at,
                    'is_critical' => $locked->is_critical,
                ]);

                activity()
                    ->on($replacement)
                    ->causedBy($actor)
                    ->withProperties([
                        'replaces_document_public_id' => $locked->public_id,
                        'doc_type' => $locked->doc_type->value,
                    ])
                    ->log('reuploaded_vendor_document');

                DB::afterCommit(fn () => event(new VendorDocumentUploaded($replacement)));

                return $replacement;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk($diskName)->delete($path);

            throw $exception;
        }
    }
}
